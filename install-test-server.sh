#!/usr/bin/env bash
set -Eeuo pipefail

DOMAIN="${1:-test.itsoft.store}"
BACKUP_DIR="${ITSOFT_BACKUP_DIR:-/root/itsoft}"
PROJECT_DIR="${ITSOFT_PROJECT_DIR:-/root/itsoft-project}"
REPOSITORY_URL="${ITSOFT_REPOSITORY_URL:-https://github.com/Slavko13/itshop.git}"
MIN_ROOT_SIZE_KB=$((15 * 1024 * 1024))
MIN_ROOT_FREE_KB=$((8 * 1024 * 1024))

fail() {
	printf '\nERROR: %s\n' "$*" >&2
	exit 1
}

step() {
	printf '\n==> %s\n' "$*"
}

on_error() {
	local exit_code="$?"
	printf '\nInstallation stopped on line %s (exit code %s).\n' "${BASH_LINENO[0]}" "$exit_code" >&2
	printf 'Fix the reported error and do not delete Docker volumes manually.\n' >&2
	exit "$exit_code"
}
trap on_error ERR

[[ "${EUID}" -eq 0 ]] || fail 'Run this installer as root.'
[[ -r /etc/os-release ]] || fail 'Unable to identify the operating system.'

# shellcheck disable=SC1091
source /etc/os-release
[[ "${ID:-}" == 'ubuntu' ]] || fail "Ubuntu is required (detected: ${PRETTY_NAME:-unknown})."

command -v df >/dev/null 2>&1 || fail 'df is not available.'
root_size_kb="$(df -Pk / | awk 'NR == 2 { print $2 }')"
root_free_kb="$(df -Pk / | awk 'NR == 2 { print $4 }')"

if (( root_size_kb < MIN_ROOT_SIZE_KB )); then
	fail "The root disk is smaller than 15 GB. This is probably the old 4 GB server."
fi

if (( root_free_kb < MIN_ROOT_FREE_KB )); then
	fail 'At least 8 GB of free space is required on the root disk.'
fi

[[ -d "$BACKUP_DIR" ]] || fail "Backup directory not found: $BACKUP_DIR"
mapfile -t backups < <(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'itsoft-full-*.tar.gz' -print)

if (( ${#backups[@]} != 1 )); then
	printf 'Found backup archives:\n' >&2
	printf '  %s\n' "${backups[@]:-none}" >&2
	fail "Exactly one itsoft-full-*.tar.gz archive must be stored in $BACKUP_DIR."
fi

backup_archive="${backups[0]}"

step 'Installing base packages'
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl git openssl ufw

step 'Checking DNS'
mapfile -t resolved_ips < <(getent ahostsv4 "$DOMAIN" | awk '$2 == "STREAM" { print $1 }' | sort -u)
(( ${#resolved_ips[@]} > 0 )) || fail "$DOMAIN does not have a public IPv4 address yet."

local_ips=" $(hostname -I 2>/dev/null || true) "
server_ip=''
for resolved_ip in "${resolved_ips[@]}"; do
	if [[ "$local_ips" == *" $resolved_ip "* ]]; then
		server_ip="$resolved_ip"
		break
	fi
done

if [[ -z "$server_ip" ]]; then
	printf 'Domain addresses: %s\n' "${resolved_ips[*]}" >&2
	printf 'This server addresses: %s\n' "${local_ips# }" >&2
	fail "$DOMAIN does not point to this server. Update its A record before continuing."
fi

step 'Installing Docker Engine and Docker Compose'
if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
	for package in docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc; do
		apt-get remove -y "$package" >/dev/null 2>&1 || true
	done

	install -m 0755 -d /etc/apt/keyrings
	curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
	chmod a+r /etc/apt/keyrings/docker.asc

	cat > /etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${UBUNTU_CODENAME:-$VERSION_CODENAME}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

	apt-get update
	apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

systemctl enable --now docker
docker info >/dev/null
docker compose version

step 'Preparing firewall rules'
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
printf 'UFW status: '
ufw status | head -n 1

step 'Downloading the project'
if [[ -d "$PROJECT_DIR/.git" ]]; then
	git -C "$PROJECT_DIR" pull --ff-only
elif [[ -e "$PROJECT_DIR" ]]; then
	fail "$PROJECT_DIR exists but is not a Git repository."
else
	git clone "$REPOSITORY_URL" "$PROJECT_DIR"
fi

cd "$PROJECT_DIR"

step 'Restoring the full backup and starting WordPress'
ITSOFT_SITE_URL="http://${server_ip}:8080" bash server-bootstrap.sh "$backup_archive"

step 'Enabling HTTPS'
bash enable-https.sh "$DOMAIN"

step 'Installing and enabling the official YooKassa plugin'
bash enable-yookassa-test.sh

step 'Final checks'
docker compose --profile https ps
site_ready=0
for _ in $(seq 1 6); do
	if curl --fail --silent --show-error --head "https://${DOMAIN}" >/dev/null 2>&1; then
		site_ready=1
		break
	fi
	sleep 5
done
(( site_ready == 1 )) || fail "Final HTTPS check failed for https://${DOMAIN}."

cat <<EOF

Installation completed successfully.

Site:    https://${DOMAIN}
Admin:   https://${DOMAIN}/wp-admin/
Backup:  ${backup_archive}
Project: ${PROJECT_DIR}

Manual step still required:
WooCommerce -> YooKassa settings -> connect the TEST store.
Do not run enable-staging-test-payment.sh; it enables the internal mock gateway.
EOF
