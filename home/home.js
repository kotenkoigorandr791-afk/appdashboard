(function () {
  const I18N = {
    en: {
      hero_title: 'Secure Cloud Shell Access',
      hero_desc1: 'ServerKherson is a web-based cloud shell application that allows users to securely access and manage remote servers.',
      hero_desc2: 'We use Google OAuth authentication to protect user accounts and ensure secure access to private server resources. Only basic profile information is requested for identification. The platform offers a variety of applications including cloud file management, media galleries, interactive maps, server monitoring, recipe management, expense tracking, health tracking, and network utilities — all accessible through a single account.',
      loading_app: 'Opening app...',
      signin: 'Sign in', register: 'Register', otp_button: 'One Time PIN',
      features_title: 'Server applications overview', apps_heading: 'Installed applications',
      promo_title: 'Unlock every app — free',
      promo_body: 'If you do not have an account yet and want to use the full potential of these applications, create one: registration is completely free.',
      promo_cta: 'Create free account', terms: 'Terms of use', privacy: 'Privacy policy',
      nav_menu_title: 'Menu', nav_home: 'Home', nav_features: 'Explore platform capabilities', nav_news: 'News',
      gallery_empty: 'No screenshots are available for this application yet.',
      portal_title: 'ServerKherson portal', portal_badge: 'Main hub',
      news_section_title: 'News', news_layout_edit: 'Edit layout', news_layout_done: 'Done',
      waiting_btn: 'Waiting...', loading_btn: 'Loading...', logging_out: 'Logging out...', news_toast_label: 'New', news_more_btn: 'View more news', news_read_more: 'Read full news',       news_empty: 'No news yet.',       cal_less: 'Less', cal_more: 'More', cal_title: 'News Calendar',
      explore_title: 'Explore more capabilities', explore_subtitle: 'Open any app to get started',
      apps: { portal: 'Landing, auth, and registration — the hub for all apps on this server', cloud: 'Cloud file manager with editors (3MF, PDF, LaTeX...)', films: 'Movie manager – collection, comments', map: 'Map viewer, KML, GPS, favorites', media_viewer: 'Media gallery with albums', performance: 'Server performance monitor', recipes: 'Custom recipes manager', spends: 'Expense tracker with charts', steps: 'Health & activity tracker', tft_generator: 'TFT screen code generator', viewer: 'Local network device viewer' },
      app_details: { portal: 'The main ServerKherson web hub: landing page, sign-in, and registration — your gateway before opening individual applications hosted on this server.', cloud: 'Comprehensive cloud storage manager with built-in editors for 3MF, PDF, LaTeX, markdown, photo manipulation, and audio transcription. Supports real-time collaboration and versioning.', films: 'Personal movie database with comments, watchlist, and categories. Track what you\'ve watched and share reviews.', map: 'Interactive map viewer supporting KML, GPS exchange formats. Save favorites, measure routes, and view terrain.', media_viewer: 'Media gallery with automatic album creation, previews for images/videos, and sharing options.', performance: 'Real-time server monitor: CPU load, memory usage, disk I/O, Apache module status. Historical graphs.', recipes: 'Create, share, and collect custom recipes. Organize by ingredients, tags, and meal types.', spends: 'Expense tracker similar to spreadsheet with charts, monthly statistics, and store-based categorization.', steps: 'Activity tracker for steps, distance, calories. Set daily goals and view progress over time.', tft_generator: 'Generate TFT screen code from canvas drawings. Convert sketches into display-ready code.', viewer: 'Discover and monitor local network devices (routers, smart devices) with dashboard view.' }
    },
    uk: {
      hero_title: 'Безпечний доступ до хмарної консолі',
      hero_desc1: 'ServerKherson — це веб-застосунок для безпечного доступу та керування серверами.',
      hero_desc2: 'Ми використовуємо Google OAuth для захисту облікових записів. Запитується лише базова інформація профілю. Платформа пропонує різноманітні застосунки: хмарне керування файлами, медіа-галереї, інтерактивні мапи, моніторинг сервера, рецепти, облік витрат, трекер активності та мережеві утиліти — все доступне через єдиний обліковий запис.',
      loading_app: 'Відкриття застосунку...',
      signin: 'Увійти', register: 'Реєстрація', otp_button: 'Одноразовий PIN',
      features_title: 'Огляд серверних застосунків', apps_heading: 'Окремі застосунки',
      promo_title: 'Увесь потенціал — безкоштовно',
      promo_body: 'Якщо у вас ще немає облікового запису й ви хочете користуватися повним потенціалом застосунків, створіть його — реєстрація повністю безкоштовна.',
      promo_cta: 'Створити акаунт', terms: 'Правила використання', privacy: 'Політика конфіденційності',
      nav_menu_title: 'Меню', nav_home: 'Головна', nav_features: 'Можливості платформи', nav_news: 'Новини',
      gallery_empty: 'Для цього застосунку поки немає скріншотів.',
      portal_title: 'Портал ServerKherson', portal_badge: 'Головний хаб',
      news_section_title: 'Новини', news_layout_edit: 'Редагувати макет', news_layout_done: 'Готово',
      waiting_btn: 'Очікування...', loading_btn: 'Завантаження...', logging_out: 'Вихід з акаунту...', news_toast_label: 'Нове', news_more_btn: 'Дивитись більше новин', news_read_more: 'Розгорнути новину',       news_empty: 'Новин ще немає.',       cal_less: 'Менше', cal_more: 'Більше', cal_title: 'Календар новин',
      explore_title: 'Дослідіть більше можливостей', explore_subtitle: 'Відкрийте будь-який додаток',
      apps: { portal: 'Головна, вхід і реєстрація — центр доступу до всіх застосунків', cloud: 'Хмарний менеджер файлів з редакторами', films: 'Менеджер фільмів – колекція, коментарі', map: 'Перегляд мап, KML, GPS, обране', media_viewer: 'Медіа галерея з альбомами', performance: 'Моніторинг сервера', recipes: 'Менеджер рецептів', spends: 'Трекер витрат з графіками', steps: 'Трекер здоров\'я і активності', tft_generator: 'Генератор коду TFT екрана', viewer: 'Переглядач пристроїв мережі' },
      app_details: { portal: 'Головний веб-хаб ServerKherson: стартова сторінка, вхід і реєстрація.', cloud: 'Повноцінний хмарний менеджер з редакторами 3MF, PDF, LaTeX, Markdown, фото та транскрипцією аудіо.', films: 'База фільмів з коментарями, списком перегляду та категоріями.', map: 'Інтерактивна мапа з підтримкою KML, GPS. Зберігайте обране, вимірюйте маршрути.', media_viewer: 'Медіа галерея з автогенерацією альбомів, попереднім переглядом фото/відео.', performance: 'Моніторинг сервера в реальному часі: CPU, пам\'ять, Apache, графіки.', recipes: 'Створюйте та діліться рецептами. Організація за інгредієнтами, тегами.', spends: 'Трекер витрат з графіками та статистикою по магазинах.', steps: 'Трекер активності: кроки, дистанція, калорії. Цілі та прогрес.', tft_generator: 'Генерація коду для TFT екрану з малюнків на полотні.', viewer: 'Виявлення пристроїв у локальній мережі (роутери, смарт-пристрої).' }
    },
    ru: {
      hero_title: 'Безопасный доступ к облачной консоли',
      hero_desc1: 'ServerKherson — это веб-приложение для безопасного доступа и управления серверами.',
      hero_desc2: 'Мы используем Google OAuth для защиты аккаунтов. Запрашивается только базовая информация профиля. Платформа предлагает разнообразные приложения: облачное управление файлами, медиа-галереи, интерактивные карты, мониторинг сервера, рецепты, учёт расходов, трекер активности и сетевые утилиты — всё доступно через единую учётную запись.',
      loading_app: 'Открытие приложения...',
      signin: 'Войти', register: 'Регистрация', otp_button: 'Одноразовый PIN',
      features_title: 'Обзор серверных приложений', apps_heading: 'Отдельные приложения',
      promo_title: 'Весь потенциал — бесплатно',
      promo_body: 'Если у вас нет аккаунта и вы хотите использовать потенциал приложений, создайте его — регистрация полностью бесплатна.',
      promo_cta: 'Создать аккаунт', terms: 'Правила использования', privacy: 'Политика конфиденциальности',
      nav_menu_title: 'Меню', nav_home: 'Главная', nav_features: 'Возможности платформы', nav_news: 'Новости',
      gallery_empty: 'Для этого приложения пока нет скриншотов.',
      portal_title: 'Портал ServerKherson', portal_badge: 'Главный хаб',
      news_section_title: 'Новости', news_layout_edit: 'Редактировать макет', news_layout_done: 'Готово',
      waiting_btn: 'Ожидание...', loading_btn: 'Загрузка...', logging_out: 'Выход из аккаунта...', news_toast_label: 'Новое', news_more_btn: 'Посмотреть больше новостей', news_read_more: 'Раскрыть новость',       news_empty: 'Новостей пока нет.',       cal_less: 'Меньше', cal_more: 'Больше', cal_title: 'Календарь новостей',
      explore_title: 'Исследуйте возможности', explore_subtitle: 'Откройте любое приложение',
      apps: { portal: 'Главная, вход и регистрация — центр доступа ко всем приложениям', cloud: 'Облачный менеджер файлов с редакторами', films: 'Менеджер фильмов – коллекция, комментарии', map: 'Карты, KML, GPS, избранное', media_viewer: 'Медиа галерея с альбомами', performance: 'Мониторинг сервера', recipes: 'Менеджер рецептов', spends: 'Учёт расходов с графиками', steps: 'Трекер здоровья и активности', tft_generator: 'Генератор кода TFT экрана', viewer: 'Просмотр устройств сети' },
      app_details: { portal: 'Главный сайт ServerKherson: главная страница, вход и регистрация.', cloud: 'Облачный менеджер с редакторами 3MF, PDF, LaTeX, Markdown, фото и транскрипцией аудио.', films: 'База фильмов с комментариями, списком просмотра, категориями.', map: 'Карты с поддержкой KML, GPS. Избранное, маршруты.', media_viewer: 'Медиа галерея с альбомами и предпросмотром.', performance: 'Мониторинг сервера: CPU, память, модули Apache, графики.', recipes: 'Рецепты: создание, обмен, сбор. По ингредиентам и тегам.', spends: 'Учет расходов как таблица с графиками и статистикой.', steps: 'Трекер активности: шаги, калории, дистанция, цели.', tft_generator: 'Генерация кода для TFT экрана из рисунков на холсте.', viewer: 'Поиск устройств в локальной сети (роутеры, умные устройства).' }
    },
    be: {
      hero_title: 'Бяспечны доступ да хмарнай кансолі',
      hero_desc1: 'ServerKherson — гэта вэб-праграма для бяспечнага доступу да сервераў.',
      hero_desc2: 'Мы выкарыстоўваем Google OAuth для абароны ўліковых запісаў. Запытваецца толькі базавая інфармацыя профілю. Платформа прапануе разнастайныя прыкладанні: воблачнае кіраванне файламі, медыя-галерэі, інтерактыўныя мапы, маніторынг сервера, рэцэпты, улік выдаткаў, трэкер актыўнасці і сеткавыя ўтыліты — усё даступна праз адзіны ўліковы запіс.',
      loading_app: 'Адкрыццё прыкладання...',
      signin: 'Увайсці', register: 'Рэгістрацыя', otp_button: 'Аднаразовы PIN',
      features_title: 'Агляд серверных прыкладанняў', apps_heading: 'Асобныя прыкладанні',
      promo_title: 'Увесь патэнцыял — бясплатна',
      promo_body: 'Калі ў вас яшчэ няма ўліковага запісу і вы хочаце карыстацца магчымасцямі прыкладанняў, стварыце яго — рэгістрацыя цалкам бясплатная.',
      promo_cta: 'Стварыць уліковы запіс', terms: 'Правілы карыстання', privacy: 'Палітыка прыватнасці',
      nav_menu_title: 'Меню', nav_home: 'Галоўная', nav_features: 'Магчымасці платформы', nav_news: 'Навіны',
      gallery_empty: 'Для гэтага прыкладання пакуль няма здымкаў экрана.',
      portal_title: 'Партал ServerKherson', portal_badge: 'Галоўны хаб',
      news_section_title: 'Навіны', news_layout_edit: 'Рэдагаваць макет', news_layout_done: 'Гатова',
      waiting_btn: 'Чаканне...', loading_btn: 'Загрузка...', logging_out: 'Выхад з уліковага запісу...', news_toast_label: 'Новае', news_more_btn: 'Паглядзець больш навін', news_read_more: 'Разгарнуць навіну',       news_empty: 'Навін яшчэ няма.',       cal_less: 'Менш', cal_more: 'Больш', cal_title: 'Календар навін',
      explore_title: 'Даследуйце магчымасці', explore_subtitle: 'Адкрыйце любы дадатак',
      apps: { portal: 'Галоўная, уваход і рэгістрацыя — цэнтр доступу да ўсіх прыкладанняў', cloud: 'Воблачны менеджар файлаў з рэдактарамі', films: 'Менеджар фільмаў – калекцыя, каментары', map: 'Мапы, KML, GPS, абранае', media_viewer: 'Медыя галерэя з альбомамі', performance: 'Маніторынг сервера', recipes: 'Менеджар рэцэптаў', spends: 'Улік выдаткаў з графікамі', steps: 'Трэкер здароўя і актыўнасці', tft_generator: 'Генератар кода TFT экрана', viewer: 'Праглядальнік прылад сеткі' },
      app_details: { portal: 'Галоўны сайт ServerKherson: старонка, уваход і рэгістрацыя.', cloud: 'Воблачны менеджар з рэдактарамі 3MF, PDF, LaTeX, Markdown, фота і транскрыпцыяй аўдыё.', films: 'База фільмаў з каментарамі, спісам прагляду, катэгорыямі.', map: 'Мапы з падтрымкай KML, GPS. Абранае, маршруты.', media_viewer: 'Медыя галерэя з альбомамі і папярэднім праглядам.', performance: 'Маніторынг сервера: CPU, памяць, модулі Apache, графікі.', recipes: 'Рэцэпты: стварэнне, абмен, збор. Па інгрэдыентах і тэгах.', spends: 'Улік выдаткаў як табліца з графікамі і статыстыкай.', steps: 'Трэкер актыўнасці: крокі, калорыі, адлегласць, мэты.', tft_generator: 'Генерацыя кода для TFT экрана з малюнкаў на палатне.', viewer: 'Пошук прылад у лакальнай сетцы (роутары, разумныя прылады).' }
    },
    pl: {
      hero_title: 'Bezpieczny dostęp do powłoki chmurowej',
      hero_desc1: 'ServerKherson to aplikacja webowa umożliwiająca bezpieczny dostęp do zdalnych serwerów.',
      hero_desc2: 'Używamy Google OAuth do ochrony kont. Pobierane są tylko podstawowe dane profilu. Platforma oferuje różnorodne aplikacje: zarządzanie plikami w chmurze, galerie mediów, interaktywne mapy, monitorowanie serwera, przepisy, śledzenie wydatków, tracker aktywności i narzędzia sieciowe — wszystko dostępne przez jedno konto.',
      loading_app: 'Otwieranie aplikacji...',
      signin: 'Zaloguj się', register: 'Rejestracja', otp_button: 'Jednorazowy PIN',
      features_title: 'Przegląd aplikacji serwerowych', apps_heading: 'Zainstalowane aplikacje',
      promo_title: 'Pełny potencjał — za darmo',
      promo_body: 'Jeśli nie masz jeszcze konta i chcesz korzystać z pełnych możliwości aplikacji, utwórz je — rejestracja jest całkowicie bezpłatna.',
      promo_cta: 'Utwórz konto', terms: 'Regulamin', privacy: 'Polityka prywatności',
      nav_menu_title: 'Menu', nav_home: 'Strona główna', nav_features: 'Możliwości platformy', nav_news: 'Aktualności',
      gallery_empty: 'Brak zrzutów ekranu dla tej aplikacji.',
      portal_title: 'Portal ServerKherson', portal_badge: 'Główny hub',
      news_section_title: 'Aktualności', news_layout_edit: 'Edytuj układ', news_layout_done: 'Gotowe',
      waiting_btn: 'Czekanie...', loading_btn: 'Ładowanie...', logging_out: 'Wylogowywanie...', news_toast_label: 'Nowe', news_more_btn: 'Zobacz więcej aktualności', news_read_more: 'Rozwiń aktualność',       news_empty: 'Brak aktualności.',       cal_less: 'Mniej', cal_more: 'Więcej', cal_title: 'Kalendarz aktualności',
      explore_title: 'Odkryj więcej możliwości', explore_subtitle: 'Otwórz dowolną aplikację',
      apps: { portal: 'Strona główna, logowanie i rejestracja — centrum dostępu do wszystkich aplikacji', cloud: 'Menedżer plików w chmurze z edytorami', films: 'Menedżer filmów – kolekcja, komentarze', map: 'Przeglądarka map, KML, GPS', media_viewer: 'Galeria mediów z albumami', performance: 'Monitor wydajności serwera', recipes: 'Menedżer przepisów', spends: 'Śledzenie wydatków z wykresami', steps: 'Śledzenie zdrowia i aktywności', tft_generator: 'Generator kodu ekranu TFT', viewer: 'Przeglądarka urządzeń sieciowych' },
      app_details: { portal: 'Główny hub ServerKherson: strona główna, logowanie i rejestracja.', cloud: 'Kompleksowy menedżer plików z edytorami 3MF, PDF, LaTeX, Markdown, zdjęć i transkrypcji audio.', films: 'Baza filmów z komentarzami, listą do obejrzenia i kategoriami.', map: 'Interaktywna mapa obsługująca KML, GPS. Ulubione, trasy.', media_viewer: 'Galeria mediów z automatycznym tworzeniem albumów.', performance: 'Monitor serwera w czasie rzeczywistym: CPU, pamięć, Apache, wykresy.', recipes: 'Twórz, udostępniaj i zbieraj przepisy. Według składników i tagów.', spends: 'Śledzenie wydatków jak arkusz kalkulacyjny z wykresami.', steps: 'Śledzenie aktywności: kroki, kalorie, dystans, cele.', tft_generator: 'Generowanie kodu dla ekranu TFT z rysunków.', viewer: 'Odkrywanie urządzeń w sieci lokalnej.' }
    },
    cs: {
      hero_title: 'Bezpečný přístup ke cloudovému shellu',
      hero_desc1: 'ServerKherson je webová aplikace umožňující bezpečný přístup ke vzdáleným serverům.',
      hero_desc2: 'Pro ochranu účtů používáme Google OAuth. Jsou vyžadovány pouze základní informace z profilu. Platforma nabízí různé aplikace: cloudovou správu souborů, mediální galerie, interaktivní mapy, monitorování serveru, recepty, sledování výdajů, tracker aktivity a síťové nástroje — vše dostupné přes jeden účet.',
      loading_app: 'Otevírání aplikace...',
      signin: 'Přihlásit se', register: 'Registrace', otp_button: 'Jednorázový PIN',
      features_title: 'Přehled serverových aplikací', apps_heading: 'Nainstalované aplikace',
      promo_title: 'Plný potenciál — zdarma',
      promo_body: 'Pokud ještě nemáte účet a chcete využívat plný potenciál aplikací, vytvořte si ho — registrace je zcela zdarma.',
      promo_cta: 'Vytvořit účet', terms: 'Podmínky použití', privacy: 'Zásady ochrany soukromí',
      nav_menu_title: 'Nabídka', nav_home: 'Domů', nav_features: 'Možnosti platformy', nav_news: 'Novinky',
      gallery_empty: 'Pro tuto aplikaci zatím nejsou dostupné žádné snímky obrazovky.',
      portal_title: 'Portál ServerKherson', portal_badge: 'Hlavní uzel',
      news_section_title: 'Novinky', news_layout_edit: 'Upravit rozložení', news_layout_done: 'Hotovo',
      waiting_btn: 'Čekání...', loading_btn: 'Načítání...', logging_out: 'Odhlášení...', news_toast_label: 'Nové', news_more_btn: 'Zobrazit více novinek', news_read_more: 'Rozbalit novinku',       news_empty: 'Zatím žádné novinky.',       cal_less: 'Méně', cal_more: 'Více', cal_title: 'Kalendář novinek',
      explore_title: 'Prozkoumejte další možnosti', explore_subtitle: 'Otevřete libovolnou aplikaci',
      apps: { portal: 'Hlavní stránka, přihlášení a registrace — centrum přístupu ke všem aplikacím', cloud: 'Správce souborů v cloudu s editory', films: 'Správce filmů – sbírka, komentáře', map: 'Prohlížeč map, KML, GPS', media_viewer: 'Mediální galerie s alby', performance: 'Monitor výkonu serveru', recipes: 'Správce receptů', spends: 'Sledování výdajů s grafy', steps: 'Sledování zdraví a aktivity', tft_generator: 'Generátor kódu obrazovky TFT', viewer: 'Prohlížeč síťových zařízení' },
      app_details: { portal: 'Hlavní hub ServerKherson: hlavní stránka, přihlášení a registrace.', cloud: 'Komplexní správce souborů s editory 3MF, PDF, LaTeX, Markdown, fotek a přepisu zvuku.', films: 'Databáze filmů s komentáři, seznamem ke zhlédnutí a kategoriemi.', map: 'Interaktivní mapa s podporou KML, GPS. Oblíbené, trasy.', media_viewer: 'Mediální galerie s automatickým vytvářením alb.', performance: 'Monitor serveru v reálném čase: CPU, paměť, Apache, grafy.', recipes: 'Vytvářejte a sdílejte recepty. Podle ingrediencí a tagů.', spends: 'Sledování výdajů jako tabulka s grafy.', steps: 'Sledování aktivity: kroky, kalorie, vzdálenost, cíle.', tft_generator: 'Generování kódu pro TFT obrazovku z kreseb.', viewer: 'Zjišťování zařízení v místní síti.' }
    }
  };

  const APPS = [
    { id: 'cloud', name: 'Cloud' },
    { id: 'films', name: 'Films' },
    { id: 'map', name: 'Map' },
    { id: 'media_viewer', name: 'Media Viewer' },
    { id: 'performance', name: 'Performance' },
    { id: 'recipes', name: 'Recipes' },
    { id: 'spends', name: 'Spends' },
    { id: 'steps', name: 'Steps' },
    { id: 'tft_generator', name: 'TFT Generator' },
    { id: 'viewer', name: 'Network Viewer' }
  ];

  const ICONS = {
    cloud: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/></svg>`,
    films: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.296 3.464 3.02 3.956"/><path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3z"/><path d="M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="m6.18 5.276 3.1 3.899"/></svg>`,
    map: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z"/><path d="M9 4v13M15 7v13"/></svg>`,
    media_viewer: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.033 9.44a.647.647 0 0 1 0 1.12l-4.065 2.352a.645.645 0 0 1-.968-.56V7.648a.645.645 0 0 1 .967-.56z"/><path d="M7 21h10"/><rect width="20" height="14" x="2" y="3" rx="2"/></svg>`,
    performance: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2h4"/><path d="M12 14v-4"/><path d="M4 13a8 8 0 0 1 8-7 8 8 0 1 1-5.3 14L4 17.6"/><path d="M9 17H4v5"/></svg>`,
    recipes: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17V5a2 2 0 0 0-2-2H4"/><path d="M8 21h12a2 2 0 0 0 2-2v-1a1 1 0 0 0-1-1H11a1 1 0 0 0-1 1v1a2 2 0 1 1-4 0V5a2 2 0 1 0-4 0v2a1 1 0 0 0 1 1h3"/></svg>`,
    spends: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>`,
    steps: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v-2.38C4 11.5 2.97 10.5 3 8c.03-2.72 1.49-6 4.5-6C9.37 2 10 3.8 10 5.5c0 3.11-2 5.66-2 8.68V16a2 2 0 1 1-4 0Z"/><path d="M20 20v-2.38c0-2.12 1.03-3.12 1-5.62-.03-2.72-1.49-6-4.5-6C14.63 6 14 7.8 14 9.5c0 3.11 2 5.66 2 8.68V20a2 2 0 1 0 4 0Z"/><path d="M16 17h4"/><path d="M4 13h4"/></svg>`,
    tft_generator: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 16 4-4-4-4"/><path d="m6 8-4 4 4 4"/><path d="m14.5 4-5 16"/></svg>`,
    portal: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="8" x="3" y="3" rx="1.5"/><rect width="8" height="8" x="13" y="3" rx="1.5"/><rect width="8" height="8" x="3" y="13" rx="1.5"/><rect width="8" height="8" x="13" y="13" rx="1.5"/></svg>`,
    viewer: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>`
  };

  const PREV_ORIG = 'preview/';
  const PREV_WEB = 'preview/web/';
  const PREVIEWS = {
    portal: ['site_home.jpg', 'site_login.jpg', 'site_register.jpg'],
    cloud: ['cloud_code.jpg', 'cloud_exel.jpg', 'cloud_latex.jpg', 'cloud_markdown.jpg', 'cloud_presentation.jpg', 'cloud_word.jpg'],
    films: ['films_comments.jpg', 'films_main.jpg', 'films_picture-in-picture.jpg', 'films_player.jpg', 'films_settings_bar.jpg'],
    map: ['maps_add_route.jpg', 'maps_api_docs.jpg', 'maps__main_layout.jpg', 'maps_main_table.jpg', 'maps_settings_bar.jpg'],
    media_viewer: ['gallery_download_app.jpg', 'gallery_home.jpg'],
    performance: ['performance_charts.jpg', 'performance_graph.jpg', 'performance_main.jpg'],
    recipes: ['recipe_product_card.jpg', 'recipes_main.jpg'],
    spends: [], steps: [], viewer: [],
    tft_generator: ['tft_code_generator.jpg']
  };

  function webSrc(f) { return PREV_WEB + f.replace(/\.(jpe?g|png)$/i, '.webp'); }

  let lang = 'en', theme = 'dark';
  let currentImages = [], currentIndex = 0;
  let ruNotifyTimer = null, engagementScheduled = false;
  const PROMO_KEY = 'sk_home_promo_dismissed';
  const NEWS_SEEN_KEY = 'sk_news_seen_ids';
  const isNewsRoute = location.pathname.replace(/\/+$/, '').toLowerCase() === '/news';
  const NEWS_PAGE_SIZE = 9;
  const LANG_OPTIONS = {
    en: { label: 'English', flag: 'lang-flag-en' },
    uk: { label: 'Українська', flag: 'lang-flag-uk' },
    ru: { label: 'Русский', flag: 'lang-flag-ru' },
    be: { label: 'Беларуская', flag: 'lang-flag-be' },
    pl: { label: 'Polski', flag: 'lang-flag-pl' },
    cs: { label: 'Čeština', flag: 'lang-flag-cs' }
  };

  const $ = id => document.getElementById(id);
  const grid = $('featuresGrid');
  const spotlight = $('portalSpotlight');
  const modal = $('modal');
  const modalTitle = $('modalTitle');
  const modalDesc = $('modalDescription');
  const modalGallery = $('modalGallery');
  const lightbox = $('lightbox');
  const lbImg = $('lightboxImg');
  const lbCounter = $('lightboxCounter');

  function closeLanguageMenu() {
    const selector = $('langSelector');
    const button = $('langSelectorButton');
    if (!selector || !button) return;
    selector.classList.remove('open');
    button.setAttribute('aria-expanded', 'false');
  }

  function updateLanguageMenu(l) {
    const selector = $('langSelector');
    const button = $('langSelectorButton');
    if (!selector || !button) return;
    const item = LANG_OPTIONS[l] || LANG_OPTIONS.en;
    selector.dataset.value = l;
    button.querySelector('.lang-selector-label').textContent = item.label;
    const flag = button.querySelector('.lang-flag');
    flag.className = 'lang-flag ' + item.flag;
    selector.querySelectorAll('.lang-selector-option').forEach(option => {
      const active = option.dataset.lang === l;
      option.classList.toggle('active', active);
      option.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }

  function initLanguageMenu() {
    const selector = $('langSelector');
    const button = $('langSelectorButton');
    if (!selector || !button) return;
    button.addEventListener('click', e => {
      e.stopPropagation();
      const open = selector.classList.toggle('open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    selector.querySelectorAll('.lang-selector-option').forEach(option => {
      option.addEventListener('click', e => {
        e.stopPropagation();
        setLanguage(option.dataset.lang || 'en');
        closeLanguageMenu();
      });
    });
    document.addEventListener('click', e => {
      if (!selector.contains(e.target)) closeLanguageMenu();
    });
  }

  // ── News state ──────────────────────────────────────────────────────────────
  let newsItems = [];
  let visibleNewsItems = [];
  let newsPage = 1;
  let newsSectionVisible = false;
  let currentNewsIndex = 0;

  function todayKey() { const d = new Date(); return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`; }
  function isFirstVisitToday() {
    const k = 'sk_news_visit_date';
    try {
      const stored = localStorage.getItem(k);
      const today = todayKey();
      if (stored === today) return false;
      localStorage.setItem(k, today);
      return true;
    } catch { return false; }
  }
  function isToday(ts) {
    if (!ts) return false;
    const d = new Date(ts * 1000);
    const now = new Date();
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
  }
  function todayNews(items) { return items.filter(n => isToday(n.created_at)); }

  function escH(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function mdToHtml(text) {
    if (!text) return '';
    let html = escH(text);
    // code blocks
    html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    // inline code
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    // headings
    html = html.replace(/^###### (.+)$/gm, '<h6>$1</h6>');
    html = html.replace(/^##### (.+)$/gm, '<h5>$1</h5>');
    html = html.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
    // horizontal rules
    html = html.replace(/^---$/gm, '<hr>');
    // blockquotes
    html = html.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>');
    // images
    html = html.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" loading="lazy">');
    // links
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    // strikethrough
    html = html.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    // bold+italic
    html = html.replace(/\*\*\*([^*]+)\*\*\*/g, '<strong><em>$1</em></strong>');
    // bold
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    // italic
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    // ordered lists
    html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>\n?)+/g, '<ol>$&</ol>');
    // unordered lists
    html = html.replace(/^[-*] (.+)$/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>\n?)+/g, function(m) { return m.includes('<ol>') ? m : '<ul>' + m.replace(/<ol>/g,'').replace(/<\/ol>/g,'') + '</ul>'; });
    // paragraphs (double newline)
    html = html.replace(/\n\n/g, '</p><p>');
    html = '<p>' + html + '</p>';
    // clean empty paragraphs and wrapping
    html = html.replace(/<p><\/p>/g, '');
    html = html.replace(/<p><hr><\/p>/g, '<hr>');
    html = html.replace(/<p><\/?([ou]l)>/g, '<$1>');
    html = html.replace(/<\/([ou]l)><\/p>/g, '</$1>');
    html = html.replace(/<p><h([1-6])>/g, '<h$1>');
    html = html.replace(/<\/(h[1-6])><\/p>/g, '</$1>');
    html = html.replace(/<p><blockquote>/g, '<blockquote>');
    html = html.replace(/<\/blockquote><\/p>/g, '</blockquote>');
    html = html.replace(/<p><pre>/g, '<pre>');
    html = html.replace(/<\/pre><\/p>/g, '</pre>');
    html = html.replace(/<p><img /g, '<img ');
    html = html.replace(/<\/p><\/li>/g, '</li>');
    html = html.replace(/<li><p>/g, '<li>');
    return html;
  }

  function stripMarkdown(text) {
    if (!text) return '';
    let s = text;
    s = s.replace(/```[\s\S]*?```/g, '');
    s = s.replace(/`([^`]+)`/g, '$1');
    s = s.replace(/^#{1,6} /gm, '');
    s = s.replace(/\*\*\*([^*]+)\*\*\*/g, '$1');
    s = s.replace(/\*\*([^*]+)\*\*/g, '$1');
    s = s.replace(/\*([^*]+)\*/g, '$1');
    s = s.replace(/~~([^~]+)~~/g, '$1');
    s = s.replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1');
    s = s.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
    s = s.replace(/^[-*] /gm, '');
    s = s.replace(/^\d+\. /gm, '');
    s = s.replace(/^&gt; /gm, '');
    s = s.replace(/---/g, '');
    return s.trim();
  }

  function fmtDate(ts) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    return d.toLocaleDateString(lang === 'en' ? 'en-GB' : lang, { day: '2-digit', month: 'short', year: 'numeric' });
  }

  // ── Fetch and render news ────────────────────────────────────────────────────
  function loadNews() {
    fetch('/main?api=news&action=list')
      .then(r => r.json())
      .then(data => {
        if (!data.ok) {
          if (isNewsRoute) {
            visibleNewsItems = [];
            showNewsSection();
          }
          return;
        }
        newsItems = data.items || [];
        if (isNewsRoute) {
          visibleNewsItems = newsItems;
          showNewsSection();
        } else {
          visibleNewsItems = todayNews(newsItems);
          if (visibleNewsItems.length) showNewsSection();
          else hideNewsSection();
        }
        checkTodayToast();
      })
      .catch(() => {
        if (isNewsRoute) {
          visibleNewsItems = [];
          showNewsSection();
        }
      });
  }

  function showNewsSection() {
    const section = $('newsSection');
    if (!section) return;
    newsSectionVisible = true;
    section.style.display = '';
    renderNewsCards();
    applyNewsTexts();
  }

  function hideNewsSection() {
    const section = $('newsSection');
    if (section) section.style.display = 'none';
    newsSectionVisible = false;
  }

  function renderNewsCards() {
    const canvas = $('newsCanvas');
    if (!canvas) return;
    const t = I18N[lang] || I18N.en;
    if (!visibleNewsItems.length) {
      canvas.innerHTML = `<div class="news-empty">${escH(t.news_empty)}</div>`;
      renderNewsPager();
      return;
    }
    if (isNewsRoute) {
      renderNewsFeed();
    } else {
      renderNewsGrid();
    }
  }

  function renderNewsGrid() {
    const canvas = $('newsCanvas');
    if (!canvas) return;
    canvas.style.display = '';
    const t = I18N[lang] || I18N.en;
    canvas.innerHTML = '';
    visibleNewsItems.forEach(item => {
      const card = document.createElement('div');
      card.className = 'news-card';
      card.dataset.id = item.id;
      card.innerHTML = `
${item.photo ? `<img class="news-card-img" src="/${escH(item.photo)}" alt="" loading="lazy">` : ''}
<div class="news-card-body">
  ${isToday(item.created_at) ? `<div class="news-new-badge"><svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5 5.14 1.18 7.09L12 18.27l-6.18 3.23L7 14.41 2 9.27l7.1-1.01z"/></svg>${escH(t.news_toast_label)}</div>` : ''}
  <div class="news-card-title">${escH(item.title)}</div>
  ${item.body ? `<div class="news-card-preview">${escH(stripMarkdown(item.body))}</div>` : ''}
  <div class="news-card-date">${fmtDate(item.created_at)}</div>
</div>`;
      card.addEventListener('click', () => openNewsModal(item));
      canvas.appendChild(card);
    });
    // Add "more news" card at the end
    const moreCard = document.createElement('a');
    moreCard.className = 'news-card news-more-card';
    moreCard.href = '/news' + location.search;
    moreCard.addEventListener('click', function(e) {
      e.preventDefault();
      const url = new URL(this.href, location.origin);
      url.searchParams.set('lang', lang);
      url.searchParams.set('theme', theme);
      location.href = url.toString();
    });
    moreCard.innerHTML = `<div class="news-more-card-body"><div class="news-more-card-icon"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 18l5-5-5-5 M15 18l5-5-5-5"/></svg></div><div class="news-more-card-label">${escH(t.news_more_btn)}</div></div>`;
    canvas.appendChild(moreCard);
    renderNewsPager();
  }

  // ── News Feed (list view for /news route) ─────────────────────────────────
  function renderNewsFeed() {
    const canvas = $('newsCanvas');
    if (!canvas) return;
    canvas.style.display = 'block';
    const t = I18N[lang] || I18N.en;
    canvas.innerHTML = `
      <div class="news-feed-layout">
        <div class="news-feed" id="newsFeed"></div>
        <div class="news-roadmap" id="newsRoadmap">
          <div class="news-roadmap-header">${escH(t.nav_news)}</div>
          <div class="news-roadmap-list" id="newsRoadmapList"></div>
        </div>
      </div>`;
    currentNewsIndex = Math.min(currentNewsIndex, visibleNewsItems.length - 1);
    renderFeedItems();
    renderRoadmapItems();
    if (visibleNewsItems.length > NEWS_PAGE_SIZE) renderFeedNav();
    setTimeout(() => scrollToFeedItem(currentNewsIndex), 80);
  }

  function renderFeedItems() {
    const feed = $('newsFeed');
    if (!feed) return;
    const t = I18N[lang] || I18N.en;
    feed.innerHTML = '';
    visibleNewsItems.forEach((item, i) => {
      const el = document.createElement('div');
      el.className = 'news-feed-item' + (i === currentNewsIndex ? ' active' : '');
      el.id = 'newsFeedItem-' + i;
      el.dataset.index = i;
      const hasBody = !!item.body;
      el.innerHTML = `
        <div class="news-feed-item-inner">
          ${item.photo ? `<img class="news-feed-img" src="/${escH(item.photo)}" alt="" loading="lazy">` : ''}
          <div class="news-feed-body">
            ${isToday(item.created_at) ? `<div class="news-new-badge"><svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5 5.14 1.18 7.09L12 18.27l-6.18 3.23L7 14.41 2 9.27l7.1-1.01z"/></svg>${escH(t.news_toast_label)}</div>` : ''}
            <div class="news-feed-title">${escH(item.title)}</div>
            <div class="news-feed-date">${fmtDate(item.created_at)}</div>
            ${hasBody ? `<div class="news-feed-preview">${escH(stripMarkdown(item.body))}</div>` : ''}
            <div class="news-feed-readmore"${hasBody ? '' : ' style="display:none"'}>${escH(t.news_read_more)} →</div>
          </div>
        </div>`;
      el.addEventListener('click', () => openNewsModal(item));
      feed.appendChild(el);
    });
    // Hide readmore on items where body text does not overflow the -webkit-line-clamp
    feed.querySelectorAll('.news-feed-item').forEach(el => {
      const preview = el.querySelector('.news-feed-preview');
      const readmore = el.querySelector('.news-feed-readmore');
      if (!preview || !readmore) return;
      if (preview.scrollHeight <= preview.clientHeight) {
        readmore.style.display = 'none';
      }
    });
  }

  function renderRoadmapItems() {
    const list = $('newsRoadmapList');
    if (!list) return;
    list.innerHTML = '';
    visibleNewsItems.forEach((item, i) => {
      const el = document.createElement('div');
      el.className = 'news-roadmap-item' + (i === currentNewsIndex ? ' active' : '');
      el.dataset.index = i;
      const marker = document.createElement('div');
      marker.className = 'roadmap-marker';
      const dot = document.createElement('div');
      dot.className = 'roadmap-dot';
      marker.appendChild(dot);
      el.appendChild(marker);
      const content = document.createElement('div');
      content.className = 'roadmap-content';
      content.innerHTML = `<div class="roadmap-title">${escH(item.title)}</div><div class="roadmap-date">${fmtDate(item.created_at)}</div>`;
      el.appendChild(content);
      el.addEventListener('click', e => {
        e.stopPropagation();
        updateNewsActiveItem(i);
        scrollToFeedItem(i);
      });
      list.appendChild(el);
    });
  }

  function renderFeedNav() {
    const feed = $('newsFeed');
    if (!feed) return;
    const existing = feed.querySelector('.news-feed-nav');
    if (existing) existing.remove();
    const nav = document.createElement('div');
    nav.className = 'news-feed-nav';
    nav.innerHTML = `
      <button class="news-feed-nav-btn prev-btn"${currentNewsIndex <= 0 ? ' disabled' : ''}>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <span class="news-feed-nav-counter">${currentNewsIndex + 1} · ${visibleNewsItems.length}</span>
      <button class="news-feed-nav-btn next-btn"${currentNewsIndex >= visibleNewsItems.length - 1 ? ' disabled' : ''}>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
      </button>`;
    nav.querySelector('.prev-btn').addEventListener('click', () => navigateNews(-1));
    nav.querySelector('.next-btn').addEventListener('click', () => navigateNews(1));
    feed.appendChild(nav);
  }

  function navigateNews(dir) {
    const newIdx = currentNewsIndex + dir;
    if (newIdx < 0 || newIdx >= visibleNewsItems.length) return;
    currentNewsIndex = newIdx;
    scrollToFeedItem(currentNewsIndex);
    updateNewsActiveItem(currentNewsIndex);
    updateFeedNavButtons();
  }

  function scrollToFeedItem(index) {
    const el = document.getElementById('newsFeedItem-' + index);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function updateNewsActiveItem(index) {
    currentNewsIndex = index;
    document.querySelectorAll('.news-feed-item').forEach(el => {
      el.classList.toggle('active', parseInt(el.dataset.index) === index);
    });
    document.querySelectorAll('.news-roadmap-item').forEach(el => {
      el.classList.toggle('active', parseInt(el.dataset.index) === index);
    });
    const roadmapList = $('newsRoadmapList');
    if (roadmapList) {
      const activeEl = roadmapList.querySelector('.news-roadmap-item.active');
      if (activeEl) activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  function updateFeedNavButtons() {
    const nav = document.querySelector('.news-feed-nav');
    if (!nav) return;
    const prevBtn = nav.querySelector('.prev-btn');
    const nextBtn = nav.querySelector('.next-btn');
    const counter = nav.querySelector('.news-feed-nav-counter');
    prevBtn.disabled = currentNewsIndex <= 0;
    nextBtn.disabled = currentNewsIndex >= visibleNewsItems.length - 1;
    if (counter) counter.textContent = `${currentNewsIndex + 1} · ${visibleNewsItems.length}`;
  }

  function renderNewsPager() {
    let pager = $('newsPager');
    if (!pager) {
      pager = document.createElement('div');
      pager.id = 'newsPager';
      pager.className = 'news-pager';
      $('newsSection')?.appendChild(pager);
    }
    if (!isNewsRoute || visibleNewsItems.length <= NEWS_PAGE_SIZE) {
      pager.innerHTML = '';
      return;
    }
    const pages = Math.ceil(visibleNewsItems.length / NEWS_PAGE_SIZE);
    pager.innerHTML = '';
    for (let i = 1; i <= pages; i++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = i;
      btn.className = i === newsPage ? 'active' : '';
      btn.addEventListener('click', () => {
        newsPage = i;
        renderNewsCards();
        $('newsSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
      pager.appendChild(btn);
    }
  }

  function applyNewsTexts() {
    const t = I18N[lang] || I18N.en;
    const el = document.querySelector('[data-i18n="news_section_title"]');
    if (el) el.textContent = t.news_section_title;
    const navBtn = document.querySelector('[data-i18n="nav_news"]');
    if (navBtn) navBtn.textContent = t.nav_news;

  }

  // ── News read modal ──────────────────────────────────────────────────────────
  const newsModal = $('newsModal');
  const newsModalClose = $('newsModalClose');
  const newsModalBody = $('newsModalBody');

  function openNewsModal(item) {
    if (!newsModal || !newsModalBody) return;
    const idx = visibleNewsItems.indexOf(item);
    const t = I18N[lang] || I18N.en;
    newsModalBody.innerHTML = `
${item.photo ? `<img src="/${escH(item.photo)}" alt="">` : ''}
<div class="news-article-surface">
  <h2>${escH(item.title)}</h2>
  <div class="news-modal-date">${fmtDate(item.created_at)}</div>
  <div class="news-md-body">${mdToHtml(item.body || '')}</div>
</div>
${visibleNewsItems.length > 1 ? `<div class="news-modal-nav">
  <button class="news-modal-nav-btn prev-btn" data-news-dir="-1"${idx <= 0 ? ' disabled' : ''}>
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>
    <span>${escH(t.news_toast_label === 'New' ? 'Previous' : 'Попередня')}</span>
  </button>
  <span class="news-modal-nav-counter">${idx + 1} / ${visibleNewsItems.length}</span>
  <button class="news-modal-nav-btn next-btn" data-news-dir="1"${idx >= visibleNewsItems.length - 1 ? ' disabled' : ''}>
    <span>${escH(t.news_toast_label === 'New' ? 'Next' : 'Наступна')}</span>
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>
  </button>
</div>` : ''}`;
    newsModal.classList.add('active');
    const navBtns = newsModalBody.querySelectorAll('.news-modal-nav-btn');
    navBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const dir = parseInt(btn.dataset.newsDir);
        const newIdx = idx + dir;
        if (newIdx < 0 || newIdx >= visibleNewsItems.length) return;
        openNewsModal(visibleNewsItems[newIdx]);
        if (isNewsRoute) {
          updateNewsActiveItem(newIdx);
          scrollToFeedItem(newIdx);
          if (document.querySelector('.news-feed-nav')) updateFeedNavButtons();
        }
      });
    });
  }

  if (newsModalClose) newsModalClose.addEventListener('click', () => newsModal.classList.remove('active'));
  if (newsModal) newsModal.addEventListener('click', e => { if (e.target === newsModal) newsModal.classList.remove('active'); });
  if (newsModal) newsModal.addEventListener('mousemove', e => {
    const box = newsModal.querySelector('.news-article-surface');
    if (!box) return;
    const r = box.getBoundingClientRect();
    box.style.setProperty('--mx', `${e.clientX - r.left}px`);
    box.style.setProperty('--my', `${e.clientY - r.top}px`);
  });

  // ── Today news star-badge toast ───────────────────────────────────────────
  function checkTodayToast() {
    if (!isFirstVisitToday()) return;
    const todayNews = newsItems.filter(n => isToday(n.created_at));
    if (!todayNews.length) return;
    setTimeout(() => showStarToast(todayNews[0]), 3000);
  }

  function showStarToast(item) {
    const toast = $('newsToast');
    if (!toast) return;
    const t = I18N[lang] || I18N.en;
    toast.innerHTML = `<div class="news-toast-inner">
<div class="news-toast-star" aria-hidden="true">
  <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5 5.14 1.18 7.09L12 18.27l-6.18 3.23L7 14.41 2 9.27l7.1-1.01z"/></svg>
  <span>${escH(t.news_toast_label)}</span>
</div>
<div class="news-toast-text">
  <div class="news-toast-title">${escH(item.title)}</div>
</div>
<button class="news-toast-close" id="newsToastClose" aria-label="Close">&times;</button>
</div>`;
    toast.style.display = 'block';
    toast.querySelector('.news-toast-inner').addEventListener('click', e => {
      if (e.target.closest('#newsToastClose')) return;
      toast.style.display = 'none';
      showNewsSection();
      $('newsSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    toast.querySelector('#newsToastClose').addEventListener('click', e => {
      e.stopPropagation();
      toast.style.display = 'none';
    });
    setTimeout(() => { if (toast) toast.style.display = 'none'; }, 14000);
  }

  // ── App cards ────────────────────────────────────────────────────────────────
  function renderCards(l) {
    const t = I18N[l] || I18N.en;
    spotlight.innerHTML = '';
    const sp = document.createElement('div');
    sp.className = 'portal-spotlight-card';
    sp.innerHTML = `<div class="portal-head-row">${ICONS.portal}<span class="portal-badge">${t.portal_badge}</span></div><h3>${t.portal_title}</h3><p class="short-desc">${t.apps.portal}</p>`;
    sp.addEventListener('click', () => {
      const t = I18N[l] || I18N.en;
      const timer = setTimeout(() => loadingOverlay(t.loading_app), 500);
      showModal('portal', l);
      clearTimeout(timer);
    });
    spotlight.appendChild(sp);
    grid.innerHTML = '';
    APPS.forEach(app => {
      const card = document.createElement('div');
      card.className = 'feature-card';
      card.innerHTML = `${ICONS[app.id] || ICONS.cloud}<h3>${app.name}</h3><p class="short-desc">${t.apps[app.id] || ''}</p>`;
      card.addEventListener('click', () => {
        const t2 = I18N[l] || I18N.en;
        const timer = setTimeout(() => loadingOverlay(t2.loading_app), 500);
        showModal(app.id, l);
        clearTimeout(timer);
      });
      card.addEventListener('mouseenter', (e) => handleAppMouseEnter(e, card, app.name, t.apps[app.id] || ''));
      card.addEventListener('mouseleave', hideAppTooltip);
      card.addEventListener('mousemove', (e) => updateTooltipPosition(e, card));
      grid.appendChild(card);
    });
  }

  let tooltipTimer = null;
  let appTooltip = null;

  function handleAppMouseEnter(e, card, name, desc) {
    if (tooltipTimer) clearTimeout(tooltipTimer);
    tooltipTimer = setTimeout(() => {
      showAppTooltip(card, name, desc);
    }, 3000);
  }

  function hideAppTooltip() {
    if (tooltipTimer) {
      clearTimeout(tooltipTimer);
      tooltipTimer = null;
    }
    if (appTooltip) {
      appTooltip.classList.remove('visible');
      appTooltip = null;
    }
  }

  function showAppTooltip(card, name, desc) {
    hideAppTooltip();
    appTooltip = document.createElement('div');
    appTooltip.className = 'app-tooltip';
    appTooltip.textContent = `${name}${desc ? ' — ' + desc : ''}`;
    document.body.appendChild(appTooltip);
    updateTooltipPosition(null, card);
    appTooltip.classList.add('visible');
  }

  function updateTooltipPosition(e, card) {
    if (!appTooltip) return;
    const cardRect = card.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const tooltipWidth = appTooltip.offsetWidth || 200;
    const midpoint = cardRect.left + cardRect.width / 2;
    const isRightSide = midpoint > viewportWidth / 2;
    const sideClass = isRightSide ? 'right-side' : 'left-side';
    appTooltip.classList.remove('left-side', 'right-side');
    appTooltip.classList.add(sideClass);
    if (isRightSide) {
      appTooltip.style.left = `${cardRect.right + 10}px`;
    } else {
      appTooltip.style.left = `${cardRect.left - tooltipWidth - 10}px`;
    }
    appTooltip.style.top = `${cardRect.top + cardRect.height / 2 - appTooltip.offsetHeight / 2}px`;
  }

  function showModal(id, l) {
    const ol = $('pageLoadingOverlay');
    if (ol) ol.remove();
    const t = I18N[l] || I18N.en;
    const name = id === 'portal' ? t.portal_title : (APPS.find(a => a.id === id)?.name || id);
    modalTitle.textContent = name;
    modalDesc.textContent = t.app_details[id] || '';
    modalGallery.innerHTML = '';
    const files = (PREVIEWS[id] || []).slice();
    currentImages = files.map(f => PREV_ORIG + f);
    if (!files.length) {
      const p = document.createElement('p');
      p.className = 'gallery-empty';
      p.textContent = t.gallery_empty;
      modalGallery.appendChild(p);
    } else {
      files.forEach((f, i) => {
        const img = document.createElement('img');
        img.className = 'gallery-img';
        img.src = webSrc(f);
        img.onerror = function () { this.onerror = null; this.src = PREV_ORIG + f; };
        img.alt = name + ' preview';
        img.loading = 'lazy';
        img.addEventListener('click', e => { e.stopPropagation(); openLightbox(i); });
        modalGallery.appendChild(img);
      });
    }
    modal.classList.add('active');
  }

  function openLightbox(i) {
    currentIndex = i;
    lbImg.src = currentImages[i];
    lbCounter.textContent = (i + 1) + ' / ' + currentImages.length;
    lightbox.classList.add('active');
  }

  function moveLightbox(dir) {
    if (!currentImages.length) return;
    currentIndex = (currentIndex + dir + currentImages.length) % currentImages.length;
    lbImg.src = currentImages[currentIndex];
    lbCounter.textContent = (currentIndex + 1) + ' / ' + currentImages.length;
  }

  function applyTexts(l) {
    const t = I18N[l] || I18N.en;
    document.querySelector('[data-i18n="hero_title"]').textContent = t.hero_title;
    document.querySelector('[data-i18n="hero_desc1"]').textContent = t.hero_desc1;
    document.querySelector('[data-i18n="hero_desc2"]').textContent = t.hero_desc2;
    document.querySelectorAll('[data-i18n="signin"]').forEach(el => { el.textContent = t.signin; });
    document.querySelectorAll('[data-i18n="register"]').forEach(el => { el.textContent = t.register; });
    document.querySelectorAll('[data-i18n="otp_button"]').forEach(el => { el.textContent = t.otp_button; });
    document.querySelector('[data-i18n="features_title"]').textContent = t.features_title;
    document.querySelector('[data-i18n="apps_heading"]').textContent = t.apps_heading;
    document.querySelector('[data-i18n="terms"]').textContent = t.terms;
    document.querySelector('[data-i18n="privacy"]').textContent = t.privacy;
    document.querySelector('[data-i18n="nav_menu_title"]').textContent = t.nav_menu_title;
    document.querySelector('[data-i18n="nav_home"]').textContent = t.nav_home;
    document.querySelector('[data-i18n="nav_features"]').textContent = t.nav_features;
    applyNewsTexts();
    const calLess = document.querySelector('[data-i18n="cal_less"]');
    if (calLess) calLess.textContent = t.cal_less;
    const calMore = document.querySelector('[data-i18n="cal_more"]');
    if (calMore) calMore.textContent = t.cal_more;
    const calTitle = document.querySelector('[data-i18n="cal_title"]');
    if (calTitle) calTitle.textContent = t.cal_title;
    if (newsSectionVisible) {
      visibleNewsItems = isNewsRoute ? newsItems : todayNews(newsItems);
      renderNewsCards();
    }
    refreshToastI18n();
  }

  function setLanguage(l) {
    lang = l;
    updateLanguageMenu(l);
    applyTexts(l);
    renderCards(l);
    if (l === 'ru') {
      scheduleRuNotify();
    } else {
      if (ruNotifyTimer) { clearTimeout(ruNotifyTimer); ruNotifyTimer = null; }
      const n = $('ruNotify');
      if (n) n.remove();
    }
  }

  function setTheme(t) {
    theme = t;
    document.body.classList.toggle('light-theme', t === 'light');
    document.querySelectorAll('#themeToggle button').forEach(b => b.classList.toggle('active', b.dataset.theme === t));
  }

  function loadingOverlay(t) {
    const existing = $('pageLoadingOverlay');
    if (existing) existing.remove();
    const overlay = document.createElement('div');
    overlay.id = 'pageLoadingOverlay'; overlay.className = 'loading-overlay';
    overlay.textContent = t; document.body.appendChild(overlay);
  }

  function bindAuthLink(el) {
    if (!el) return;
    el.addEventListener('click', e => {
      e.preventDefault();
      const t = I18N[lang] || I18N.en;
      el.textContent = t.waiting_btn || 'Waiting...';
      el.style.pointerEvents = 'none';
      el.style.opacity = '0.7';
      const timer = setTimeout(() => loadingOverlay(t.loading_app || 'Opening app...'), 500);
      const url = new URL(el.href, location.origin);
      url.searchParams.set('lang', lang);
      url.searchParams.set('theme', theme);
      location.href = url.toString();
    });
  }

  function scheduleRuNotify() {
    if (ruNotifyTimer) clearTimeout(ruNotifyTimer);
    ruNotifyTimer = setTimeout(() => {
      if (lang !== 'ru' || $('ruNotify')) return;
      const n = document.createElement('div');
      n.className = 'lang-notify'; n.id = 'ruNotify';
      n.innerHTML = `Switch to English or Ukrainian? <button id="notifyEN"><span class="lang-flag lang-flag-en" style="width:16px;height:11px;border-radius:2px;vertical-align:-2px;display:inline-block;flex:0 0 16px;"></span> EN</button><button id="notifyUK"><span class="lang-flag lang-flag-uk" style="width:16px;height:11px;border-radius:2px;vertical-align:-2px;display:inline-block;flex:0 0 16px;"></span> UA</button><button id="notifyClose">✕</button>`;
      document.body.appendChild(n);
      $('notifyEN').onclick = () => { setLanguage('en'); n.remove(); };
      $('notifyUK').onclick = () => { setLanguage('uk'); n.remove(); };
      $('notifyClose').onclick = () => n.remove();
    }, 5000);
  }

  function refreshToastI18n() {
    const t = I18N[lang] || I18N.en;
    const el = $('engagementToast');
    if (!el) return;
    el.querySelector('.engagement-toast-title').textContent = t.promo_title;
    el.querySelector('.engagement-toast-body').textContent = t.promo_body;
    el.querySelector('.engagement-toast-cta').textContent = t.promo_cta;
  }

  function showEngagementPromo() {
    if ($('engagementToast')) return;
    try { if (sessionStorage.getItem(PROMO_KEY) === '1') return; } catch (e) { return; }
    const t = I18N[lang] || I18N.en;
    const toast = document.createElement('div');
    toast.id = 'engagementToast'; toast.className = 'engagement-toast'; toast.setAttribute('role', 'status');
    toast.innerHTML = `<div class="engagement-toast-inner"><div class="engagement-toast-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div><div class="engagement-toast-text"><div class="engagement-toast-title"></div><p class="engagement-toast-body"></p><a href="/register" class="engagement-toast-cta btn-primary"></a></div><button type="button" class="engagement-toast-close" aria-label="Close">&times;</button></div>`;
    document.body.appendChild(toast);
    toast.querySelector('.engagement-toast-title').textContent = t.promo_title;
    toast.querySelector('.engagement-toast-body').textContent = t.promo_body;
    const cta = toast.querySelector('.engagement-toast-cta');
    cta.textContent = t.promo_cta;
    cta.addEventListener('click', e => {
      e.preventDefault();
      const url = new URL(cta.href, location.origin);
      url.searchParams.set('lang', lang); url.searchParams.set('theme', theme);
      location.href = url.toString();
    });
    toast.querySelector('.engagement-toast-close').addEventListener('click', () => {
      try { sessionStorage.setItem(PROMO_KEY, '1'); } catch (e) {}
      toast.remove();
    });
  }

  function hasAuthCookies() {
    try {
      const c = document.cookie;
      return c.indexOf('trusted_device_hint=') !== -1
        || c.indexOf('sk_user_has_account=') !== -1;
    } catch { return false; }
  }

  function scheduleEngagement() {
    try { if (sessionStorage.getItem(PROMO_KEY) === '1') return; } catch (e) { return; }
    if (hasAuthCookies()) return;
    if (engagementScheduled) return;
    engagementScheduled = true;
    setTimeout(showEngagementPromo, 30000 + Math.random() * 90000);
  }

  const EXPLORE_KEY = 'sk_explore_dismissed';
  let exploreWidgetShown = false;

  function scheduleExploreWidget() {
    if (isNewsRoute) return;
    if (exploreWidgetShown) return;
    try { if (sessionStorage.getItem(EXPLORE_KEY) === '1') return; } catch (e) { return; }
    exploreWidgetShown = true;
    setTimeout(showExploreWidget, 45000 + Math.random() * 30000);
  }

  function showExploreWidget() {
    if ($('exploreWidget')) return;
    const t = I18N[lang] || I18N.en;
    const allApps = [{ id: 'portal', name: t.portal_title, desc: t.apps.portal, icon: ICONS.portal }].concat(
      APPS.map(a => ({ id: a.id, name: a.name, desc: t.apps[a.id] || '', icon: ICONS[a.id] || '' }))
    );
    const widget = document.createElement('div');
    widget.id = 'exploreWidget';
    widget.className = 'explore-widget';
    widget.innerHTML = `<div class="explore-widget-inner">
<div class="explore-widget-head">
  <div class="explore-widget-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div>
  <div class="explore-widget-head-text">
    <div class="explore-widget-title">${escH(t.explore_title)}</div>
    <div class="explore-widget-subtitle">${escH(t.explore_subtitle)}</div>
  </div>
  <button type="button" class="explore-widget-close" aria-label="Close">&times;</button>
</div>
<div class="explore-widget-list" id="exploreWidgetList"></div>
</div>`;
    document.body.appendChild(widget);
    const list = widget.querySelector('#exploreWidgetList');
    allApps.forEach(app => {
      const item = document.createElement('div');
      item.className = 'explore-app-item';
      item.innerHTML = `<div class="explore-app-icon">${app.icon}</div><div><div class="explore-app-name">${escH(app.name)}</div><div class="explore-app-desc">${escH(app.desc)}</div></div>`;
      item.addEventListener('click', () => {
        widget.remove();
        showModal(app.id, lang);
      });
      list.appendChild(item);
    });
    widget.querySelector('.explore-widget-close').addEventListener('click', () => {
      try { sessionStorage.setItem(EXPLORE_KEY, '1'); } catch (e) {}
      widget.remove();
    });
  }

  function openNav() {
    $('navBackdrop').classList.add('visible');
    $('navDrawer').classList.add('open');
    $('navDrawer').setAttribute('aria-hidden', 'false');
    $('navOpenBtn').setAttribute('aria-expanded', 'true');
  }

  function closeNav() {
    $('navBackdrop').classList.remove('visible');
    $('navDrawer').classList.remove('open');
    $('navDrawer').setAttribute('aria-hidden', 'true');
    $('navOpenBtn').setAttribute('aria-expanded', 'false');
  }

  $('navOpenBtn').addEventListener('click', openNav);
  $('navCloseBtn').addEventListener('click', closeNav);
  $('navBackdrop').addEventListener('click', closeNav);
  const seenNav = new Set();
  document.querySelectorAll('.nav-drawer-links button').forEach(btn => {
    const key = btn.querySelector('[data-i18n]')?.dataset.i18n || btn.id || btn.textContent.trim();
    if (seenNav.has(key)) btn.remove();
    else seenNav.add(key);
  });
  $('navGoHomeBtn').addEventListener('click', () => {
    closeNav();
    if (isNewsRoute) { location.href = '/' + location.search; return; }
    $('heroSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  $('navGoNewsBtn').addEventListener('click', () => {
    closeNav();
    if (!isNewsRoute) { location.href = '/news' + location.search; return; }
    if (!newsSectionVisible) { showNewsSection(); }
    setTimeout(() => $('newsSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 60);
  });
  $('navGoFeaturesBtn').addEventListener('click', () => {
    closeNav();
    if (isNewsRoute) { location.href = '/' + location.search + '#apps'; return; }
    $('appsSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  $('modalClose').addEventListener('click', () => modal.classList.remove('active'));
  window.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });

  $('lightboxClose').addEventListener('click', () => lightbox.classList.remove('active'));
  $('lightboxPrev').addEventListener('click', () => moveLightbox(-1));
  $('lightboxNext').addEventListener('click', () => moveLightbox(1));
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeLanguageMenu();
      if ($('navDrawer').classList.contains('open')) { closeNav(); return; }
      if (lightbox.classList.contains('active')) { lightbox.classList.remove('active'); return; }
      if (modal.classList.contains('active')) { modal.classList.remove('active'); return; }
      if (newsModal && newsModal.classList.contains('active')) { newsModal.classList.remove('active'); return; }
    }
    if (lightbox.classList.contains('active')) {
      if (e.key === 'ArrowLeft') moveLightbox(-1);
      if (e.key === 'ArrowRight') moveLightbox(1);
    }
  });

  initLanguageMenu();
  document.querySelectorAll('#themeToggle button').forEach(b => b.addEventListener('click', () => setTheme(b.dataset.theme)));

  ['loginLink', 'registerLink', 'otpLink', 'drawerLoginLink', 'drawerRegisterLink', 'drawerOtpLink'].forEach(id => bindAuthLink($(id)));
  ['termsLink', 'privacyLink'].forEach(id => bindAuthLink($(id)));

  $('emailLink').addEventListener('click', e => {
    e.preventDefault();
    window.open(`https://mail.google.com/mail/?view=cm&fs=1&to=home260khersonserver@gmail.com&su=${encodeURIComponent('File Viewer Feedback')}&body=${encodeURIComponent('Hello.')}`, '_blank');
  });

  $('year').textContent = new Date().getFullYear();

  // Auto-hide header brand after 30s to save reading space (mobile only)
  setTimeout(() => {
    if (window.matchMedia('(max-width: 600px)').matches) {
      document.querySelector('.site-header')?.classList.add('brand-hidden');
    }
  }, 30000);

  const params = new URLSearchParams(location.search);
  setTheme(params.get('theme') || 'dark');
  setLanguage(params.get('lang') || 'en');
  if (isNewsRoute) {
    $('heroSection').style.display = 'none';
    $('appsSection').style.display = 'none';
  } else {
    scheduleEngagement();
    scheduleExploreWidget();
    if (location.hash === '#apps') setTimeout(() => $('appsSection')?.scrollIntoView({ block: 'start' }), 80);
  }
  loadNews();

  // ── News calendar (GitHub-style) ──────────────────────────────────────────
  (function initCalendar() {
    const calBtn = $('calendarBtn');
    const popover = $('calendarPopover');
    const grid = $('calGrid');
    const weekdaysEl = $('calWeekdays');
    const monthLabel = $('calMonthLabel');
    const prevBtn = $('calPrev');
    const nextBtn = $('calNext');
    if (!calBtn || !popover || !grid) return;

    let calYear, calMonth;
    const now = new Date();
    calYear = now.getFullYear();
    calMonth = now.getMonth();

    const dayNames = { en: ['Mo','Tu','We','Th','Fr','Sa','Su'], uk: ['Пн','Вт','Ср','Чт','Пт','Сб','Нд'], ru: ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'], be: ['Пн','Ат','Ср','Чц','Пт','Сб', 'Нд'], pl: ['Pn','Wt','Śr','Cz','Pt','Sb','Nd'], cs: ['Po','Út','St','Čt','Pá','So','Ne'] };
    const monthNames = { en: ['January','February','March','April','May','June','July','August','September','October','November','December'], uk: ['Січень','Лютий','Березень','Квітень','Травень','Червень','Липень','Серпень','Вересень','Жовтень','Листопад','Грудень'], ru: ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'], be: ['Студзень','Люты','Сакавік','Красавік','Май','Чэрвень','Ліпень','Жнівень','Верасень','Кастрычнік','Лістапад','Снежань'], pl: ['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'], cs: ['Leden','Únor','Březen','Duben','Květen','Červen','Červenec','Srpen','Září','Říjen','Listopad','Prosinec'] };

    function renderWeekdays() {
      const names = dayNames[lang] || dayNames.en;
      weekdaysEl.innerHTML = '';
      names.forEach(d => { const s = document.createElement('span'); s.className = 'cal-weekday'; s.textContent = d; weekdaysEl.appendChild(s); });
    }

    function dateKey(y, m, d) { return y + '-' + m + '-' + d; }

    function getNewsBounds() {
      let min = Infinity, max = -Infinity;
      for (let i = 0; i < newsItems.length; i++) {
        const ts = newsItems[i].created_at;
        if (!ts) continue;
        const dt = new Date(ts * 1000);
        const v = dt.getFullYear() * 12 + dt.getMonth();
        if (v < min) min = v;
        if (v > max) max = v;
      }
      return { min, max };
    }

    function updateNavButtons() {
      const bounds = getNewsBounds();
      const cur = calYear * 12 + calMonth;
      const curMonth = now.getFullYear() * 12 + now.getMonth();
      prevBtn.disabled = bounds.min === Infinity || cur <= bounds.min;
      nextBtn.disabled = bounds.max === -Infinity || cur >= Math.min(bounds.max, curMonth);
    }

    function newsCountForKey(key) {
      let count = 0;
      for (let i = 0; i < newsItems.length; i++) {
        const ts = newsItems[i].created_at;
        if (!ts) continue;
        const dt = new Date(ts * 1000);
        if (dateKey(dt.getFullYear(), dt.getMonth(), dt.getDate()) === key) count++;
      }
      return count;
    }

    function renderCalendar() {
      const names = monthNames[lang] || monthNames.en;
      monthLabel.textContent = names[calMonth] + ' ' + calYear;
      renderWeekdays();
      grid.innerHTML = '';
      const firstDay = new Date(calYear, calMonth, 1);
      let startDow = firstDay.getDay() - 1;
      if (startDow < 0) startDow = 6;
      const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
      const prevMonthDays = new Date(calYear, calMonth, 0).getDate();
      for (let i = 0; i < startDow; i++) {
        const e = document.createElement('span');
        e.className = 'cal-cell empty';
        const dayNum = prevMonthDays - startDow + 1 + i;
        const num = document.createElement('span');
        num.className = 'cal-day-num';
        num.textContent = dayNum;
        e.appendChild(num);
        grid.appendChild(e);
      }
      for (let d = 1; d <= daysInMonth; d++) {
        const key = dateKey(calYear, calMonth, d);
        const count = newsCountForKey(key);
        const cell = document.createElement('span');
        cell.className = 'cal-cell' + (count ? ' has-news' : '');
        const numSpan = document.createElement('span');
        numSpan.className = 'cal-day-num';
        numSpan.textContent = d;
        cell.appendChild(numSpan);
        const bucket = count >= 4 ? 4 : count >= 3 ? 3 : count >= 2 ? 2 : count >= 1 ? 1 : 0;
        if (bucket) cell.dataset.count = bucket;
        if (d === now.getDate() && calMonth === now.getMonth() && calYear === now.getFullYear()) cell.classList.add('today');
        if (count) {
          const dt = new Date(calYear, calMonth, d);
          const dateStr = dt.toLocaleDateString(lang === 'en' ? 'en-GB' : lang, { day: '2-digit', month: 'short', year: 'numeric' });
          cell.addEventListener('mouseenter', function() {
            const tip = document.createElement('div');
            tip.className = 'cal-tooltip';
            tip.textContent = count + ' ' + (count === 1 ? 'news' : 'news') + ' · ' + dateStr;
            this.appendChild(tip);
          });
          cell.addEventListener('mouseleave', function() { const t = this.querySelector('.cal-tooltip'); if (t) t.remove(); });
          cell.addEventListener('click', (function(y, m, day) {
            return function() {
              popover.style.display = 'none';
              calBtn.setAttribute('aria-expanded', 'false');
              const k = dateKey(y, m, day);
              let matched = [];
              for (let i = 0; i < newsItems.length; i++) {
                const ts = newsItems[i].created_at;
                if (!ts) continue;
                const dt = new Date(ts * 1000);
                if (dateKey(dt.getFullYear(), dt.getMonth(), dt.getDate()) === k) matched.push(newsItems[i]);
              }
              if (!matched.length) return;
              if (!isNewsRoute) {
                visibleNewsItems = matched;
                showNewsSection();
                setTimeout(function() { $('newsSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60);
              }
              openNewsModal(matched[0]);
            };
          })(calYear, calMonth, d));
        }
        grid.appendChild(cell);
      }
      const totalCells = startDow + daysInMonth;
      const trailing = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
      for (let i = 1; i <= trailing; i++) {
        const e = document.createElement('span');
        e.className = 'cal-cell empty';
        const num = document.createElement('span');
        num.className = 'cal-day-num';
        num.textContent = i;
        e.appendChild(num);
        grid.appendChild(e);
      }
      const legendLabels = popover.querySelectorAll('[data-i18n]');
      legendLabels.forEach(function(el) { const k = el.dataset.i18n; const t = I18N[lang] || I18N.en; if (t[k]) el.textContent = t[k]; });
      updateNavButtons();
    }

    calBtn.addEventListener('click', function() {
      const visible = popover.style.display !== 'none';
      popover.style.display = visible ? 'none' : 'block';
      calBtn.setAttribute('aria-expanded', String(!visible));
      if (!visible) renderCalendar();
    });

    document.addEventListener('click', function(e) {
      if (popover.style.display === 'none') return;
      if (!popover.contains(e.target) && e.target !== calBtn && !calBtn.contains(e.target)) {
        popover.style.display = 'none';
        calBtn.setAttribute('aria-expanded', 'false');
      }
    });

    prevBtn.addEventListener('click', function() {
      calMonth--;
      if (calMonth < 0) { calMonth = 11; calYear--; }
      renderCalendar();
    });
    nextBtn.addEventListener('click', function() {
      calMonth++;
      if (calMonth > 11) { calMonth = 0; calYear++; }
      renderCalendar();
    });
  })();
})();
