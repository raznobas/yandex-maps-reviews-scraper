# Интеграция отзывов Яндекс.Карт

Небольшой прототип на Laravel 12 и Vue 3. Он принимает ссылку на организацию в Яндекс.Картах, подтягивает доступные отзывы, складывает их в MySQL и показывает рейтинг с постраничным списком отзывов. Без попытки сделать «платформу». Тут важнее показать рабочий поток: ссылка → синхронизация → кэш → интерфейс.

## Стек

- PHP 8.2, Laravel 12
- Vue 3.5, Composition API, Pinia 3
- MySQL 8
- Vite, Tailwind CSS 4
- PHPUnit 11
- Docker Compose

## Что умеет проект

- Вход под заранее созданным пользователем.
- Одна активная организация Яндекс.Карт на пользователя.
- Проверка и нормализация ссылок на организацию, включая короткие ссылки вида `https://yandex.ru/maps/-/...`.
- Ручной запуск синхронизации отзывов.
- Хранение среднего рейтинга, количества оценок, количества отзывов и самих отзывов в базе.
- API-пагинация отзывов по 50 штук на страницу.

Данные для входа:

```text
Email: test@example.com
Password: password
```

## Запуск через Docker

Docker поднимает PHP-FPM, Nginx, MySQL, worker очереди и Node/Vite.

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
docker compose run --rm node npm run build
docker compose up
```

После запуска приложение будет здесь:

```text
http://localhost:8080
```

Настройки Docker по умолчанию:

- `DB_HOST=mysql`
- `DB_DATABASE=yamap_integration`
- `DB_USERNAME=yamap`
- `DB_PASSWORD=yamap`
- `APP_URL=http://localhost:8080`
- `QUEUE_CONNECTION=database`

Эти значения явно заданы в `docker-compose.yml` для контейнеров `app` и `queue`. Это сделано специально: локальный `.env` может быть настроен под PhpStorm или внешний MySQL вроде `DB_HOST=MySQL-8.0`, а внутри Docker такого хоста нет.

Порты:

- приложение: `8080`
- Vite dev server: `5173`
- MySQL на хосте: `3307`, внутри Docker: `3306`

Если правите фронтенд, можно держать `docker compose up node` и работать через Vite. Для обычной проверки достаточно `npm run build`: собранные assets отдаст Laravel через Nginx.

## Ручной запуск

Нужны PHP 8.2 с MySQL-расширениями, Composer, Node.js 22 или свежий LTS, MySQL 8 и база `yamap_integration`.

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev
```

Если MySQL стоит локально, а не в Docker, поменяйте в `.env` эти значения:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yamap_integration
DB_USERNAME=root
DB_PASSWORD=
APP_URL=http://localhost:8000
```

## Переменные окружения

Основные:

- `APP_NAME` — имя приложения для Laravel и Vite.
- `APP_URL` — адрес, по которому браузер открывает приложение.
- `DB_*` — подключение к MySQL. В этом локальном проекте тесты используют обычную базу `yamap_integration`.
- `SESSION_DRIVER` — для приложения `database`, в PHPUnit используется `array`.
- `QUEUE_CONNECTION` — для Docker и ручного worker обычно `database`, в PHPUnit используется `sync`.

Настройки парсера:

- `YANDEX_REVIEWS_MAX_REVIEWS` — максимум отзывов за один запуск синхронизации.
- `YANDEX_REVIEWS_PAGE_SIZE` — размер страницы для внутренних запросов отзывов.
- `YANDEX_REVIEWS_TIMEOUT` — общий HTTP timeout в секундах.
- `YANDEX_REVIEWS_CONNECT_TIMEOUT` — timeout подключения в секундах.
- `YANDEX_REVIEWS_RETRY_ATTEMPTS` — число повторов для запросов к Яндексу.
- `YANDEX_REVIEWS_RETRY_SLEEP` — пауза между повторами в миллисекундах.
- `YANDEX_REVIEWS_PAGE_URL` — необязательный шаблон URL для страниц отзывов.

## Как работает парсер

Сначала сервис открывает публичную страницу отзывов Яндекс.Карт для сохраненного id организации. Первый HTML разбирается через `DOMDocument` и `DOMXPath`: оттуда берутся видимые счетчики рейтинга и блоки отзывов.

Дальше сервис пытается вытащить контекст, который нужен самому Яндексу для подгрузки следующих страниц: business id, CSRF token, session id, request id и retpath. Если контекста хватает, следующие страницы запрашиваются через внутренний endpoint `fetchReviews`. Подпись query-строки считается в `YandexScraperService`.

Отзывы дедуплицируются по Yandex review id и сохраняются в таблицу `reviews`. Интерфейс после этого читает только нашу базу. Переключение страниц в UI не дергает Яндекс заново — это обычная Laravel-пагинация по кэшу.

Короткие ссылки Яндекс.Карт резолвит отдельный сервис через Laravel HTTP client. TLS-проверка остается включенной, заданы connect timeout и общий timeout. Валидация URL живет в FormRequest, а сетевой переход по короткой ссылке не размазан по контроллеру.

## Ограничения Яндекса

Официального публичного API отзывов для этого сценария нет. Поэтому проект опирается на публичный HTML и внутренний endpoint Яндекс.Карт. Они могут измениться без предупреждения.

Какие проблемы приложение показывает явно:

- ссылка невалидная или ведет не на карточку организации;
- страница Яндекса недоступна;
- отзывов нет;
- Яндекс вернул captcha или bot-protection;
- разметка или внутренний API изменились, и парсер сломался;
- первые отзывы загрузились, а следующая страница упала.

## Кэширование

Парсинг запускается при сохранении организации или вручную через кнопку обновления. Результат сохраняется в MySQL: рейтинг, счетчики и отзывы. Потом Laravel API отдает сохраненные отзывы по 50 штук на страницу.

Так интерфейс не ходит в Яндекс при каждом переключении страницы. Пользователь видит стабильный список, а ошибки синхронизации не прячутся где-то в логах: статус и текст ошибки остаются у организации.

## Проверки

```bash
composer validate --no-check-publish
composer lint
php artisan test --compact
npm run quality
npm run build
docker compose config
```

`composer lint` запускает Laravel Pint в check-режиме. `npm run quality` проверяет форматирование Prettier и собирает фронтенд.

## Деплой

- Сгенерируйте реальный `APP_KEY` для окружения.
- Подключите MySQL и выполните `php artisan migrate --seed`.
- Для синхронизации отзывов используйте `QUEUE_CONNECTION=database` и запущенный worker.
- Соберите фронтенд через `npm run build`.
- Не ставьте слишком длинные timeout/retry для парсера: лучше получить понятную ошибку, чем подвесить запрос.
- Логи стоит оставить доступными, потому что bot-protection и поломка парсера без них диагностируются вслепую.

## Что я бы улучшил дальше

- Добавил бы отдельный polling API и историю синхронизаций, а не только текущий статус.
- Сделал бы плановое обновление отзывов с backoff.
- Хранил бы диагностические снимки ответов Яндекса для разбора поломок парсера.
- Добавил бы proxy или browser fallback для случаев с anti-bot.
- Поддержал бы несколько организаций на одного пользователя.

