# Running the scheduler and queue on Lightsail

Two background processes Laravel needs that a plain web server does not start
for you. Both are prerequisites for anything queued or scheduled — push
notifications first among them. If the app moves to Laravel Cloud, both are
checkboxes there and this document can be deleted.

Paths and users below assume the app lives at `/var/www/fitnation` and PHP-FPM
runs as `www-data`. Substitute whatever is true on the instance (`ps aux | grep
php-fpm` shows the user; `bitnami` images use `bitnami` and `/opt/bitnami/…`).

## 1. Scheduler — one cron line

Laravel decides *what* is due; cron only has to tick once a minute.

```
sudo crontab -u www-data -e
```

```
* * * * * cd /var/www/fitnation && php artisan schedule:run >> /dev/null 2>&1
```

Verify with `php artisan schedule:list` (shows every scheduled command and when
it next runs) and, after a minute, `php artisan schedule:test`.

The existing certbot renewal cron, if any, is unrelated and stays.

## 2. Queue worker — a systemd service

The worker must outlive SSH sessions and restart itself after a crash or reboot.

```ini
# /etc/systemd/system/fitnation-queue.service
[Unit]
Description=FitNation queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/fitnation
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=default
Restart=always
RestartSec=5
StandardOutput=append:/var/www/fitnation/storage/logs/queue.log
StandardError=append:/var/www/fitnation/storage/logs/queue.log

[Install]
WantedBy=multi-user.target
```

```
sudo systemctl daemon-reload
sudo systemctl enable --now fitnation-queue
sudo systemctl status fitnation-queue
```

- `--max-time=3600` makes the worker exit cleanly every hour; systemd restarts
  it. This bounds any memory growth without a separate supervisor.
- `--tries=3` with `--sleep=3`: a failed job is retried three times before it
  lands in `failed_jobs`. `php artisan queue:failed` lists them,
  `queue:retry all` replays them.
- One worker is enough for the current volume. A second is
  `systemctl edit` → `ExecStart` with a different `--queue`, or a template unit.

## 3. Every deploy

Workers hold the old code in memory until they restart. After pulling and
running migrations:

```
php artisan queue:restart
```

This asks running workers to exit after their current job; systemd brings them
back on the new code. Forgetting this is the classic "the fix is deployed but
the job still fails" symptom.

## 4. `.env` additions for notifications

```
QUEUE_CONNECTION=database        # already set
NOTIFICATIONS_ENABLED=true       # false on any non-production host
EXPO_ACCESS_TOKEN=...            # from expo.dev → project → Credentials → Push Notifications
```

## 5. Sizing

A PHP worker process idles at roughly 40–80 MB. On a 1 GB Lightsail instance
running MySQL alongside, check `free -m` after enabling the service; if the
box is tight, `--max-time` can be lowered and MySQL's `innodb_buffer_pool_size`
is the usual thing to trim.

## Checklist

- [ ] cron line present for the PHP-FPM user; `schedule:list` shows the
      notification commands
- [ ] `fitnation-queue.service` enabled and active
- [ ] `queue:restart` added to the deploy steps
- [ ] `.env` has `NOTIFICATIONS_ENABLED` and `EXPO_ACCESS_TOKEN`
- [ ] `storage/logs/queue.log` rotates (add to `/etc/logrotate.d/` or rely on
      Laravel's `daily` channel for application logs)
