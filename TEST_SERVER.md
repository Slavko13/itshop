# Полный перенос ITSOFT на тестовый Docker-сервер

## Что входит в полный backup

Полный архив содержит:

- SQL-дамп MariaDB со всеми таблицами WordPress/WooCommerce;
- WordPress Core и сторонние плагины из Docker volume `app_code`;
- медиатеку, приватные дистрибутивы и другие данные из volume `local_uploads`;
- manifest, исходный URL и SHA-256 checksums.

Архив содержит пользователей, заказы, адреса, лицензионные ключи и другие закрытые данные. Он исключён из Git. Передавать его следует по SSH/SCP, хранить с правами доступа только владельцу и удалять после завершения тестирования.

Внешние платежи, Telegram, Jivo и SMTP на тестовом сервере блокируются конфигурацией WordPress.

## 1. Создать полный backup на текущем компьютере

Из `J:\itshop`:

```powershell
.\backup-full.ps1
```

Архив и файл `.sha256` появятся в `.backups`.

## 2. Передать backup на тестовый сервер

```powershell
scp .backups\itsoft-full-ДАТА.tar.gz USER@SERVER:/opt/itsoft-backups/
```

Каталог `/opt/itsoft-backups` должен быть доступен только серверному пользователю.

## 3. Скачать Git и запустить всё одной командой

На новом Linux-сервере с установленными Git, Docker Engine и Docker Compose plugin:

```bash
git clone https://github.com/Slavko13/itshop.git /opt/itshop && cd /opt/itshop && ITSOFT_SITE_URL=http://SERVER_IP:8080 bash server-bootstrap.sh /opt/itsoft-backups/itsoft-full-ДАТА.tar.gz
```

Для приватного GitHub-репозитория удобнее заранее добавить SSH deploy key и заменить URL:

```bash
git clone git@github.com:Slavko13/itshop.git /opt/itshop && cd /opt/itshop && ITSOFT_SITE_URL=http://SERVER_IP:8080 bash server-bootstrap.sh /opt/itsoft-backups/itsoft-full-ДАТА.tar.gz
```

Команда:

1. клонирует исходники;
2. создаёт `.env` со случайными паролями БД и WordPress salt;
3. проверяет checksum полного backup;
4. создаёт и заполняет Docker volumes;
5. импортирует SQL;
6. собирает и запускает все контейнеры;
7. безопасно заменяет исходный URL в serialized WordPress data;
8. показывает состояние контейнеров.

Сайт открывается по значению `ITSOFT_SITE_URL`. Для первого теста это может быть `http://IP-СЕРВЕРА:8080`. Mailpit слушает только `127.0.0.1:8025` сервера и наружу не публикуется.

Bootstrap намеренно откажется перезаписывать непустые Docker volumes. Это защищает уже существующие данные от случайного повторного восстановления.

## Полный backup уже запущенного тестового сервера

```bash
bash server-backup.sh
```

Новый полный архив будет создан в `.backups`. Перед обновлением тестового сервера сначала выполняйте эту команду, а затем обычную code deployment-команду.
