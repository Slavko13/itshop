# ITSOFT

Исходники интернет-магазина ITSOFT: проектная тема WordPress, модуль выдачи лицензионных ключей, локальная Docker-среда, UI kit, тесты и документация итераций.

## Локальный запуск

```powershell
Copy-Item .env.example .env
docker compose up -d --build
```

Сайт: <http://localhost:8080>. Подробности находятся в [LOCAL_DEVELOPMENT.md](LOCAL_DEVELOPMENT.md).

## Сборка и production deployment

После однократного заполнения локального `deploy/production.local.json`:

```powershell
.\deploy.ps1 -Target production
```

Сценарий проверяет проект, собирает релиз, делает backup серверной темы и плагина и обновляет их по SSH. Полная настройка и откат описаны в [DEPLOYMENT.md](DEPLOYMENT.md).

Production-конфигурация, база, uploads, заказы, ключи и платёжные секреты не хранятся в Git и не входят в автоматический code deployment.
