#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'ERROR: %s\n' "$*" >&2
  exit 1
}

validate_wordpress_root() {
  local root="$1"
  [[ "$root" = /* ]] || fail "WordPress path must be absolute"
  [[ "$root" != "/" ]] || fail "Refusing to use filesystem root"
  [[ -d "$root/wp-content/themes" ]] || fail "Themes directory not found: $root/wp-content/themes"
  [[ -d "$root/wp-content/plugins" ]] || fail "Plugins directory not found: $root/wp-content/plugins"
}

validate_release_id() {
  [[ "$1" =~ ^[A-Za-z0-9._-]+$ ]] || fail "Invalid release id"
}

wp_cli() {
  local root="$1"
  shift
  if command -v wp >/dev/null 2>&1; then
    wp --path="$root" "$@"
  else
    return 127
  fi
}

lint_php_tree() {
  local root="$1"
  if ! command -v php >/dev/null 2>&1; then
    printf 'WARN: PHP CLI is unavailable; remote PHP lint skipped.\n'
    return 0
  fi

  local failed=0
  while IFS= read -r -d '' file; do
    if ! php -l "$file" >/dev/null; then
      printf 'PHP lint failed: %s\n' "$file" >&2
      failed=1
    fi
  done < <(find "$root" -type f -name '*.php' -print0)

  [[ "$failed" -eq 0 ]] || fail "PHP lint failed"
}

deploy_release() {
  local archive="$1"
  local wp_root="$2"
  local release_id="$3"

  validate_wordpress_root "$wp_root"
  validate_release_id "$release_id"
  [[ -f "$archive" ]] || fail "Release archive not found: $archive"

  local site_key
  site_key="$(basename "$wp_root")"
  local work_root="$wp_root/wp-content/.itsoft-deploy-$release_id"
  local stage="$work_root/stage"
  local backup_root="$HOME/.itsoft-backups/$site_key/$release_id"
  local theme_target="$wp_root/wp-content/themes/itsoft"
  local plugin_target="$wp_root/wp-content/plugins/itsoft-license-fulfillment"
  local theme_new="$wp_root/wp-content/themes/.itsoft-new-$release_id"
  local plugin_new="$wp_root/wp-content/plugins/.itsoft-new-$release_id"
  local theme_old="$wp_root/wp-content/themes/.itsoft-old-$release_id"
  local plugin_old="$wp_root/wp-content/plugins/.itsoft-old-$release_id"
  local maintenance_enabled=0
  local completed=0

  [[ -d "$theme_target" ]] || fail "Current ITSOFT theme not found: $theme_target"
  [[ -d "$plugin_target" ]] || fail "Current ITSOFT license plugin not found: $plugin_target"
  [[ ! -e "$work_root" ]] || fail "Deployment work directory already exists: $work_root"
  [[ ! -e "$backup_root" ]] || fail "Backup for release already exists: $backup_root"
  mkdir -p "$stage" "$backup_root"
  tar -xzf "$archive" -C "$stage"

  [[ -f "$stage/wp-content/themes/itsoft/style.css" ]] || fail "Theme is missing from release"
  [[ -d "$stage/wp-content/plugins/itsoft-license-fulfillment" ]] || fail "License plugin is missing from release"
  lint_php_tree "$stage/wp-content/themes/itsoft"
  lint_php_tree "$stage/wp-content/plugins/itsoft-license-fulfillment"

  if [[ -d "$theme_target" ]]; then
    cp -a "$theme_target" "$backup_root/theme"
  fi
  if [[ -d "$plugin_target" ]]; then
    cp -a "$plugin_target" "$backup_root/plugin"
  fi
  cp -a "$stage/wp-content/themes/itsoft" "$theme_new"
  cp -a "$stage/wp-content/plugins/itsoft-license-fulfillment" "$plugin_new"
  cp "$stage/deploy-manifest.json" "$backup_root/deploy-manifest.json"

  rollback_interrupted_deploy() {
    local exit_code=$?
    if [[ "$completed" -eq 0 ]]; then
      printf 'Deployment interrupted; restoring previous files...\n' >&2
      if [[ -d "$plugin_old" ]]; then
        rm -rf "$plugin_target"
        mv "$plugin_old" "$plugin_target"
      fi
      if [[ -d "$theme_old" ]]; then
        rm -rf "$theme_target"
        mv "$theme_old" "$theme_target"
      fi
    fi
    rm -rf "$theme_new" "$plugin_new" "$work_root"
    if [[ "$maintenance_enabled" -eq 1 ]]; then
      wp_cli "$wp_root" maintenance-mode deactivate >/dev/null 2>&1 || true
    fi
    exit "$exit_code"
  }
  trap rollback_interrupted_deploy ERR INT TERM

  if wp_cli "$wp_root" maintenance-mode activate >/dev/null 2>&1; then
    maintenance_enabled=1
  fi

  if [[ -d "$theme_target" ]]; then
    mv "$theme_target" "$theme_old"
  fi
  mv "$theme_new" "$theme_target"

  if [[ -d "$plugin_target" ]]; then
    mv "$plugin_target" "$plugin_old"
  fi
  mv "$plugin_new" "$plugin_target"

  wp_cli "$wp_root" core is-installed >/dev/null 2>&1 || true
  wp_cli "$wp_root" cache flush >/dev/null 2>&1 || true

  completed=1
  rm -rf "$theme_old" "$plugin_old" "$work_root"
  if [[ "$maintenance_enabled" -eq 1 ]]; then
    wp_cli "$wp_root" maintenance-mode deactivate >/dev/null 2>&1 || true
  fi
  trap - ERR INT TERM
  rm -f "$archive"

  printf 'DEPLOYED_RELEASE=%s\n' "$release_id"
  printf 'BACKUP_PATH=%s\n' "$backup_root"
}

rollback_release() {
  local wp_root="$1"
  local release_id="$2"

  validate_wordpress_root "$wp_root"
  validate_release_id "$release_id"

  local site_key
  site_key="$(basename "$wp_root")"
  local backup_root="$HOME/.itsoft-backups/$site_key/$release_id"
  local theme_target="$wp_root/wp-content/themes/itsoft"
  local plugin_target="$wp_root/wp-content/plugins/itsoft-license-fulfillment"
  local restore_root="$wp_root/wp-content/.itsoft-restore-$release_id"
  local theme_old="$wp_root/wp-content/themes/.itsoft-rollback-current-$release_id"
  local plugin_old="$wp_root/wp-content/plugins/.itsoft-rollback-current-$release_id"
  local completed=0

  [[ -d "$backup_root" ]] || fail "Backup not found: $backup_root"
  [[ -d "$backup_root/theme" ]] || fail "Theme backup not found"
  [[ -d "$backup_root/plugin" ]] || fail "Plugin backup not found"
  [[ ! -e "$restore_root" ]] || fail "Restore directory already exists: $restore_root"

  mkdir -p "$restore_root"
  cp -a "$backup_root/theme" "$restore_root/theme"
  cp -a "$backup_root/plugin" "$restore_root/plugin"
  lint_php_tree "$restore_root"

  rollback_interrupted_restore() {
    local exit_code=$?
    if [[ "$completed" -eq 0 ]]; then
      if [[ -d "$plugin_old" ]]; then
        rm -rf "$plugin_target"
        mv "$plugin_old" "$plugin_target"
      fi
      if [[ -d "$theme_old" ]]; then
        rm -rf "$theme_target"
        mv "$theme_old" "$theme_target"
      fi
    fi
    rm -rf "$restore_root"
    wp_cli "$wp_root" maintenance-mode deactivate >/dev/null 2>&1 || true
    exit "$exit_code"
  }
  trap rollback_interrupted_restore ERR INT TERM

  wp_cli "$wp_root" maintenance-mode activate >/dev/null 2>&1 || true
  mv "$theme_target" "$theme_old"
  mv "$plugin_target" "$plugin_old"
  mv "$restore_root/theme" "$theme_target"
  mv "$restore_root/plugin" "$plugin_target"
  completed=1
  rm -rf "$theme_old" "$plugin_old"
  rm -rf "$restore_root"
  wp_cli "$wp_root" cache flush >/dev/null 2>&1 || true
  wp_cli "$wp_root" maintenance-mode deactivate >/dev/null 2>&1 || true
  trap - ERR INT TERM

  printf 'ROLLED_BACK_TO_BACKUP_OF=%s\n' "$release_id"
}

command_name="${1:-}"
case "$command_name" in
  deploy)
    [[ "$#" -eq 4 ]] || fail "Usage: remote-deploy.sh deploy ARCHIVE WP_ROOT RELEASE_ID"
    deploy_release "$2" "$3" "$4"
    ;;
  rollback)
    [[ "$#" -eq 3 ]] || fail "Usage: remote-deploy.sh rollback WP_ROOT RELEASE_ID"
    rollback_release "$2" "$3"
    ;;
  *)
    fail "Unknown command: $command_name"
    ;;
esac
