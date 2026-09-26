(function () {
  const I18N = {
en: {
      hero_title: 'Secure Cloud Shell Access',
      hero_desc1: 'ServerKherson is a web-based cloud shell application that allows users to securely access and manage remote servers.',
      hero_desc2: 'We use Google OAuth authentication to protect user accounts and ensure secure access to private server resources. Only basic profile information is requested for identification. The platform offers a variety of applications including cloud file management, interactive maps, photo backup, movie collections, recipe management, expense tracking, health tracking, and development tools — all accessible through a single account.',
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
      register_tooltip: 'This is easy',
      apps: { portal: 'Landing, auth, and registration — the hub for all apps on this server', cloud: 'Cloud file manager with editors (3MF, PDF, LaTeX...)', dawarich: 'Interactive maps, routes, and favorites', films: 'Movie manager – collection, comments', gitea: 'Git repositories and code collaboration', immich: 'Photo & video backup with albums', kanboard: 'Kanban boards for projects and tasks', lopaka: 'Graphics editor for embedded displays', recipes: 'Custom recipes manager', smart_home: 'Smart home device dashboard', spends: 'Expense tracker with charts', steps: 'Health & activity tracker' },
      app_details: { portal: 'The main ServerKherson web hub: landing page, sign-in, and registration — your gateway before opening individual applications hosted on this server.', cloud: 'Comprehensive cloud storage manager with built-in editors for 3MF, PDF, LaTeX, markdown, photo manipulation, and audio transcription. Supports real-time collaboration and versioning.', dawarich: 'Interactive map viewer supporting KML, GPS exchange formats. Save favorites, measure routes, and view terrain.', films: 'Personal movie database with comments, watchlist, and categories. Track what you\'ve watched and share reviews.', gitea: 'Self-hosted Git service for repositories, issues, and pull requests with code review and collaboration.', immich: 'Self-hosted photo and video backup with albums, search, and timeline browsing.', kanboard: 'Kanban project management: boards, tasks, swimlanes, and team workflows.', lopaka: 'Graphics editor and code generator for embedded displays (TFT, U8g2, MicroPython).', recipes: 'Create, share, and collect custom recipes. Organize by ingredients, tags, and meal types.', smart_home: 'Control and monitor smart home devices from a single dashboard: sensors, switches, MQTT, and automations.', spends: 'Expense tracker similar to spreadsheet with charts, monthly statistics, and store-based categorization.', steps: 'Activity tracker for steps, distance, calories. Set daily goals and view progress over time.' }
    },
uk: {
      hero_title: 'Безпечний доступ до хмарної консолі',
      hero_desc1: 'ServerKherson — це веб-застосунок для безпечного доступу та керування серверами.',
      hero_desc2: 'Ми використовуємо Google OAuth для захисту облікових записів. Запитується лише базова інформація профілю. Платформа пропонує різноманітні застосунки: хмарне керування файлами, інтерактивні мапи, бекап фото, фільмотеку, рецепти, облік витрат, трекер активності та інструменти розробника — все доступне через єдиний обліковий запис.',
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
      register_tooltip: 'Це легко',
      apps: { portal: 'Головна, вхід і реєстрація — центр доступу до всіх застосунків', cloud: 'Хмарний менеджер файлів з редакторами', dawarich: 'Інтерактивні мапи, маршрути та обране', films: 'Менеджер фільмів – колекція, коментарі', gitea: 'Git-репозиторії та спільна розробка', immich: 'Бекап фото й відео з альбомами', kanboard: 'Kanban-дошки для проєктів і задач', lopaka: 'Графічний редактор для вбудованих дисплеїв', recipes: 'Менеджер рецептів', smart_home: 'Панель керування розумним домом', spends: 'Трекер витрат з графіками', steps: 'Трекер здоров\'я і активності' },
      app_details: { portal: 'Головний веб-хаб ServerKherson: стартова сторінка, вхід і реєстрація.', cloud: 'Повноцінний хмарний менеджер з редакторами 3MF, PDF, LaTeX, Markdown, фото та транскрипцією аудіо.', dawarich: 'Інтерактивна мапа з підтримкою KML, GPS. Зберігайте обране, вимірюйте маршрути.', films: 'База фільмів з коментарями, списком перегляду та категоріями.', gitea: 'Власний Git-сервіс: репозиторії, задачі, pull request та огляд коду.', immich: 'Самостійний бекап фото й відео з альбомами, пошуком і стрічкою часу.', kanboard: 'Kanban-керування проєктами: дошки, задачі, доріжки та командна робота.', lopaka: 'Графічний редактор і генератор коду для вбудованих дисплеїв (TFT, U8g2, MicroPython).', recipes: 'Створюйте та діліться рецептами. Організація за інгредієнтами, тегами.', smart_home: 'Керування пристроями розумного дому з однієї панелі: сенсори, перемикачі, MQTT та автоматизації.', spends: 'Трекер витрат з графіками та статистикою по магазинах.', steps: 'Трекер активності: кроки, дистанція, калорії. Цілі та прогрес.' }
    },
    ru: {
      hero_title: 'Безопасный доступ к облачной консоли',
      hero_desc1: 'ServerKherson — это веб-приложение для безопасного доступа и управления серверами.',
      hero_desc2: 'Мы используем Google OAuth для защиты аккаунтов. Запрашивается только базовая информация профиля. Платформа предлагает разнообразные приложения: облачное управление файлами, интерактивные карты, бекап фото, фільмотеку, рецепты, учёт расходов, трекер активности и инструменты разработчика — всё доступно через единую учётную запись.',
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
      register_tooltip: 'Это легко',
      apps: { portal: 'Главная, вход и регистрация — центр доступа ко всем приложениям', cloud: 'Облачный менеджер файлов с редакторами', dawarich: 'Интерактивные карты, маршруты и избранное', films: 'Менеджер фильмов – коллекция, комментарии', gitea: 'Git-репозитории и совместная разработка', immich: 'Бекап фото и видео с альбомами', kanboard: 'Kanban-доски для проектов и задач', lopaka: 'Графический редактор для встраиваемых дисплеев', recipes: 'Менеджер рецептов', smart_home: 'Панель управления умным домом', spends: 'Учёт расходов с графиками', steps: 'Трекер здоровья и активности' },
      app_details: { portal: 'Главный сайт ServerKherson: главная страница, вход и регистрация.', cloud: 'Облачный менеджер с редакторами 3MF, PDF, LaTeX, Markdown, фото и транскрипцией аудио.', dawarich: 'Интерактивные карты с поддержкой KML, GPS. Избранное, маршруты.', films: 'База фильмов с комментариями, списком просмотра, категориями.', gitea: 'Собственный Git-сервис: репозитории, задачи, pull request и обзор кода.', immich: 'Резервное копирование фото и видео с альбомами, поиском и лентой времени.', kanboard: 'Kanban-управление проектами: доски, задачи, дорожки и командная работа.', lopaka: 'Графический редактор и генератор кода для встраиваемых дисплеев (TFT, U8g2, MicroPython).', recipes: 'Рецепты: создание, обмен, сбор. По ингредиентам и тегам.', smart_home: 'Управление устройствами умного дома из одной панели: датчики, реле, MQTT и автоматизации.', spends: 'Учет расходов как таблица с графиками и статистикой.', steps: 'Трекер активности: шаги, калории, дистанция, цели.' }
    },
    be: {
      hero_title: 'Бяспечны доступ да хмарнай кансолі',
      hero_desc1: 'ServerKherson — гэта вэб-праграма для бяспечнага доступу да сервераў.',
      hero_desc2: 'Мы выкарыстоўваем Google OAuth для абароны ўліковых запісаў. Запытваецца толькі базавая інфармацыя профілю. Платформа прапануе разнастайныя прыкладанні: воблачнае кіраванне файламі, інтерактыўныя мапы, бэкап фота, фільматэка, рэцэпты, улік выдаткаў, трэкер актыўнасці і інструменты распрацоўшчыка — усё даступна праз адзіны ўліковы запіс.',
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
      apps: { portal: 'Галоўная, уваход і рэгістрацыя — цэнтр доступу да ўсіх прыкладанняў', cloud: 'Воблачны менеджар файлаў з рэдактарамі', dawarich: 'Інтэрактыўныя мапы, маршруты і абранае', films: 'Менеджар фільмаў – калекцыя, каментары', gitea: 'Git-рэпазітарыі і сумесная распрацоўка', immich: 'Бэкап фота і відэа з альбомамі', kanboard: 'Kanban-дошкі для праектаў і задач', lopaka: 'Графічны рэдактар для ўбудаваных дысплеяў', recipes: 'Менеджар рэцэптаў', smart_home: 'Панэль кіравання разумным домам', spends: 'Улік выдаткаў з графікамі', steps: 'Трэкер здароўя і актыўнасці' },
      app_details: { portal: 'Галоўны сайт ServerKherson: старонка, уваход і рэгістрацыя.', cloud: 'Воблачны менеджар з рэдактарамі 3MF, PDF, LaTeX, Markdown, фота і транскрыпцыяй аўдыё.', dawarich: 'Інтэрактыўныя мапы з падтрымкай KML, GPS. Абранае, маршруты.', films: 'База фільмаў з каментарамі, спісам прагляду, катэгорыямі.', gitea: 'Уласны Git-сэрвіс: рэпазітарыі, задачы, pull request і агляд кода.', immich: 'Рэзервовае капіраванне фота і відэа з альбомамі, пошукам і стужкай часу.', kanboard: 'Kanban-кіраванне праектамі: дошкі, задачы, дарожкі і камандная праца.', lopaka: 'Графічны рэдактар і генератар кода для ўбудаваных дысплеяў (TFT, U8g2, MicroPython).', recipes: 'Рэцэпты: стварэнне, абмен, збор. Па інгрэдыентах і тэгах.', smart_home: 'Кіраванне прыладамі разумнага дому з адной панэлі: датчыкі, рэле, MQTT і аўтаматызацыі.', spends: 'Улік выдаткаў як табліца з графікамі і статыстыкай.', steps: 'Трэкер актыўнасці: крокі, калорыі, адлегласць, мэты.' }
    },
    pl: {
      hero_title: 'Bezpieczny dostęp do powłoki chmurowej',
      hero_desc1: 'ServerKherson to aplikacja webowa umożliwiająca bezpieczny dostęp do zdalnych serwerów.',
      hero_desc2: 'Używamy Google OAuth do ochrony kont. Pobierane są tylko podstawowe dane profilu. Platforma oferuje różnorodne aplikacje: zarządzanie plikami w chmurze, interaktywne mapy, kopie zapasowe zdjęć, kolekcje filmów, przepisy, śledzenie wydatków, tracker aktywności i narzędzia deweloperskie — wszystko dostępne przez jedno konto.',
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
      apps: { portal: 'Strona główna, logowanie i rejestracja — centrum dostępu do wszystkich aplikacji', cloud: 'Menedżer plików w chmurze z edytorami', dawarich: 'Interaktywne mapy, trasy i ulubione', films: 'Menedżer filmów – kolekcja, komentarze', gitea: 'Repozytoria Git i współpraca nad kodem', immich: 'Kopia zapasowa zdjęć i wideo z albumami', kanboard: 'Tablice Kanban dla projektów i zadań', lopaka: 'Edytor graficzny dla wbudowanych wyświetlaczy', recipes: 'Menedżer przepisów', smart_home: 'Panel sterowania inteligentnym domem', spends: 'Śledzenie wydatków z wykresami', steps: 'Śledzenie zdrowia i aktywności' },
      app_details: { portal: 'Główny hub ServerKherson: strona główna, logowanie i rejestracja.', cloud: 'Kompleksowy menedżer plików z edytorami 3MF, PDF, LaTeX, Markdown, zdjęć i transkrypcji audio.', dawarich: 'Interaktywna mapa obsługująca KML, GPS. Ulubione, trasy.', films: 'Baza filmów z komentarzami, listą do obejrzenia i kategoriami.', gitea: 'Własnoręcznie hostowany serwis Git: repozytoria, zgłoszenia, pull requesty i przegląd kodu.', immich: 'Kopia zapasowa zdjęć i wideo z albumami, wyszukiwaniem i osią czasu.', kanboard: 'Zarządzanie projektami Kanban: tablice, zadania, tory i praca zespołowa.', lopaka: 'Edytor graficzny i generator kodu dla wbudowanych wyświetlaczy (TFT, U8g2, MicroPython).', recipes: 'Twórz, udostępniaj i zbieraj przepisy. Według składników i tagów.', smart_home: 'Sterowanie urządzeniami inteligentnego domu z jednego panelu: czujniki, przełączniki, MQTT i automatyzacje.', spends: 'Śledzenie wydatków jak arkusz kalkulacyjny z wykresami.', steps: 'Śledzenie aktywności: kroki, kalorie, dystans, cele.' }
    },
    cs: {
      hero_title: 'Bezpečný přístup ke cloudovému shellu',
      hero_desc1: 'ServerKherson je webová aplikace umožňující bezpečný přístup ke vzdáleným serverům.',
      hero_desc2: 'Pro ochranu účtů používáme Google OAuth. Jsou vyžadovány pouze základní informace z profilu. Platforma nabízí různé aplikace: cloudovou správu souborů, interaktivní mapy, zálohu fotek, filmové sbírky, recepty, sledování výdajů, tracker aktivity a vývojářské nástroje — vše dostupné přes jeden účet.',
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
      apps: { portal: 'Hlavní stránka, přihlášení a registrace — centrum přístupu ke všem aplikacím', cloud: 'Správce souborů v cloudu s editory', dawarich: 'Interaktivní mapy, trasy a oblíbené', films: 'Správce filmů – sbírka, komentáře', gitea: 'Repozitáře Git a spolupráce na kódu', immich: 'Záloha fotek a videí s alby', kanboard: 'Kanban tabule pro projekty a úkoly', lopaka: 'Grafický editor pro vestavěné displeje', recipes: 'Správce receptů', smart_home: 'Panel chytré domácnosti', spends: 'Sledování výdajů s grafy', steps: 'Sledování zdraví a aktivity' },
      app_details: { portal: 'Hlavní hub ServerKherson: hlavní stránka, přihlášení a registrace.', cloud: 'Komplexní správce souborů s editory 3MF, PDF, LaTeX, Markdown, fotek a přepisu zvuku.', dawarich: 'Interaktivní mapa s podporou KML, GPS. Oblíbené, trasy.', films: 'Databáze filmů s komentáři, seznamem ke zhlédnutí a kategoriemi.', gitea: 'Vlastní Git služba: repozitáře, issues, pull requesty a recenze kódu.', immich: 'Záloha fotek a videí s alby, vyhledáváním a časovou osou.', kanboard: 'Kanban řízení projektů: tabule, úkoly, dráhy a týmová práce.', lopaka: 'Grafický editor a generátor kódu pro vestavěné displeje (TFT, U8g2, MicroPython).', recipes: 'Vytvářejte a sdílejte recepty. Podle ingrediencí a tagů.', smart_home: 'Ovládání zařízení chytré domácnosti z jednoho panelu: senzory, spínače, MQTT a automatizace.', spends: 'Sledování výdajů jako tabulka s grafy.', steps: 'Sledování aktivity: kroky, kalorie, vzdálenost, cíle.' }
    }
  };

  const APPS = [
    { id: 'cloud', name: 'Cloud Storage' },
    { id: 'dawarich', name: 'Maps' },
    { id: 'films', name: 'Movie Manager' },
    { id: 'gitea', name: 'Gitea' },
    { id: 'immich', name: 'Immich' },
    { id: 'kanboard', name: 'Kanboard' },
    { id: 'lopaka', name: 'Lopaka' },
    { id: 'recipes', name: 'Recipes' },
    { id: 'smart_home', name: 'Smart Home' },
    { id: 'spends', name: 'Spending Tracker' },
    { id: 'steps', name: 'Steps Counter' }
  ];

  // Real app icons from each app directory; portal keeps the inline grid icon.
  const APP_ICON_URL = id => 'apps/' + id + '/icon.svg';
  const iconHtml = id => `<img class="app-real-icon" src="${APP_ICON_URL(id)}" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('div'),{textContent:'▪'}))">`;

  const ICONS = {
    portal: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="8" x="3" y="3" rx="1.5"/><rect width="8" height="8" x="13" y="3" rx="1.5"/><rect width="8" height="8" x="3" y="13" rx="1.5"/><rect width="8" height="8" x="13" y="13" rx="1.5"/></svg>`
  };

  const PREV_ORIG = 'preview/';
  const PREV_WEB = 'preview/web/';
  const PREVIEWS = {
    portal: ['site_home.jpg', 'site_login.jpg', 'site_register.jpg'],
    cloud: ['cloud_code.jpg', 'cloud_exel.jpg', 'cloud_latex.jpg', 'cloud_markdown.jpg', 'cloud_presentation.jpg', 'cloud_word.jpg'],
    films: ['films_comments.jpg', 'films_main.jpg', 'films_picture-in-picture.jpg', 'films_player.jpg', 'films_settings_bar.jpg'],
    dawarich: ['maps_add_route.jpg', 'maps_api_docs.jpg', 'maps__main_layout.jpg', 'maps_main_table.jpg', 'maps_settings_bar.jpg'],
    recipes: ['recipe_product_card.jpg', 'recipes_main.jpg'],
    spends: [], steps: []
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
      setTimeout(updateToastPositions, 50);
      showNewsSection();
      $('newsSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    toast.querySelector('#newsToastClose').addEventListener('click', e => {
      e.stopPropagation();
      toast.style.display = 'none';
      setTimeout(updateToastPositions, 50);
    });
    setTimeout(() => {
      if (toast) {
        toast.style.display = 'none';
        setTimeout(updateToastPositions, 50);
      }
    }, 14000);
    setTimeout(updateToastPositions, 100);
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
      card.innerHTML = `${iconHtml(app.id)}<h3>${app.name}</h3><p class="short-desc">${t.apps[app.id] || ''}</p>`;
      card.addEventListener('click', () => {
        const t2 = I18N[l] || I18N.en;
        const timer = setTimeout(() => loadingOverlay(t2.loading_app), 500);
        showModal(app.id, l);
        clearTimeout(timer);
      });
      card.addEventListener('pointerenter', (e) => handleAppMouseEnter(e, card, app.name, t.apps[app.id] || ''));
      card.addEventListener('focusin', (e) => handleAppMouseEnter(e, card, app.name, t.apps[app.id] || ''));
      card.addEventListener('pointerleave', hideAppTooltip);
      card.addEventListener('focusout', hideAppTooltip);
      card.addEventListener('mousemove', (e) => updateTooltipPosition(e, card));
      grid.appendChild(card);
    });
  }

let tooltipTimer = null;
let appTooltip = null;
let registerTooltip = null;

function handleAppMouseEnter(e, card, name, desc) {
     if (tooltipTimer) clearTimeout(tooltipTimer);
     tooltipTimer = setTimeout(() => {
         showAppTooltip(card, name, desc);
     }, 150);
 }

 function hideAppTooltip() {
     if (tooltipTimer) {
         clearTimeout(tooltipTimer);
         tooltipTimer = null;
     }
     if (appTooltip) {
         appTooltip.classList.remove('visible');
         appTooltip.remove();
         appTooltip = null;
     }
 }

 function showAppTooltip(card, name, desc) {
     hideRegisterTooltip();
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
      positionTooltip(appTooltip, card);
  }

  // Tooltip for register link
  let registerTooltipTimer = null;

  function handleRegisterMouseEnter(e) {
      if (registerTooltipTimer) clearTimeout(registerTooltipTimer);
      const link = e.currentTarget;
      registerTooltipTimer = setTimeout(() => {
          showRegisterTooltip(link);
      }, 150);
  }

  function hideRegisterTooltip() {
      if (registerTooltipTimer) {
          clearTimeout(registerTooltipTimer);
          registerTooltipTimer = null;
      }
      if (registerTooltip) {
          registerTooltip.classList.remove('visible');
          registerTooltip.remove();
          registerTooltip = null;
      }
  }

  function showRegisterTooltip(linkElement) {
      if (!linkElement) return;
      hideAppTooltip();
      hideRegisterTooltip();
      registerTooltip = document.createElement('div');
      registerTooltip.className = 'app-tooltip';
      const t = I18N[lang] || I18N.en;
      registerTooltip.textContent = t.register_tooltip || 'It\'s easy';
      document.body.appendChild(registerTooltip);
      updateRegisterTooltipPosition(null, linkElement);
      registerTooltip.classList.add('visible');
  }

  function updateRegisterTooltipPosition(e, linkElement) {
      if (!registerTooltip || !linkElement) return;
      positionTooltip(registerTooltip, linkElement);
  }

  function positionTooltip(tooltip, anchor) {
      const linkRect = anchor.getBoundingClientRect();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;
      const tooltipWidth = tooltip.offsetWidth || 200;
      const tooltipHeight = tooltip.offsetHeight || 40;
      const midpoint = linkRect.left + linkRect.width / 2;
      const sideClass = midpoint > viewportWidth / 2 ? 'left-side' : 'right-side';
      tooltip.classList.remove('left-side', 'right-side');
      tooltip.classList.add(sideClass);
      if (sideClass === 'right-side') {
          tooltip.style.left = `${Math.min(linkRect.right + 10, viewportWidth - tooltipWidth - 8)}px`;
      } else {
          tooltip.style.left = `${Math.max(linkRect.left - tooltipWidth - 10, 8)}px`;
      }
      tooltip.style.top = `${Math.max(8, Math.min(linkRect.top + linkRect.height / 2 - tooltipHeight / 2, viewportHeight - tooltipHeight - 8))}px`;
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
     
     // Add tooltip for register link
     if (el.id === 'registerLink' || el.id === 'drawerRegisterLink') {
         el.addEventListener('pointerenter', handleRegisterMouseEnter);
         el.addEventListener('focusin', handleRegisterMouseEnter);
         el.addEventListener('pointerleave', hideRegisterTooltip);
         el.addEventListener('focusout', hideRegisterTooltip);
     }
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

  function updateToastPositions() {
    const stack = [$('newsToast'), $('engagementToast'), $('exploreWidget')]
      .filter(el => el && el.style.display !== 'none');
    let offset = 0;
    stack.forEach(el => {
      el.style.bottom = (22 + offset) + 'px';
      offset += el.offsetHeight + 14;
    });
  }

  function showEngagementPromo() {
    // Показывать промо только незарегистрированным: если пользователь уже
    // имеет аккаунт (cookie ставится при логине) — не показываем вовсе.
    try {
      if (('; ' + document.cookie + ';').indexOf('; sk_user_has_account=1;') !== -1) return;
    } catch (e) {}
    if ($('engagementToast')) return;
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
      setTimeout(updateToastPositions, 50);
    });
    setTimeout(updateToastPositions, 100);
  }

  function scheduleEngagement() {
    if (engagementScheduled) return;
    engagementScheduled = true;
    // Показываем только тем, кто долго находится на странице и не имеет аккаунта
    setTimeout(showEngagementPromo, 30000);
  }

  const EXPLORE_KEY = 'sk_explore_dismissed';
  let exploreWidgetShown = false;

  function scheduleExploreWidget() {
    if (isNewsRoute) return;
    if (exploreWidgetShown) return;
    exploreWidgetShown = true;
    setTimeout(showExploreWidget, 5000);
  }

  function showExploreWidget() {
    if ($('exploreWidget')) return;
    const t = I18N[lang] || I18N.en;
    const allApps = [{ id: 'portal', name: t.portal_title, desc: t.apps.portal, icon: ICONS.portal }].concat(
      APPS.map(a => ({ id: a.id, name: a.name, desc: t.apps[a.id] || '', icon: iconHtml(a.id) }))
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
        setTimeout(updateToastPositions, 50);
        showModal(app.id, lang);
      });
      list.appendChild(item);
    });
    widget.querySelector('.explore-widget-close').addEventListener('click', () => {
      try { sessionStorage.setItem(EXPLORE_KEY, '1'); } catch (e) {}
      widget.remove();
      setTimeout(updateToastPositions, 50);
    });
    setTimeout(updateToastPositions, 100);
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
  window.addEventListener('resize', updateToastPositions);
  document.querySelectorAll('#themeToggle button').forEach(b => b.addEventListener('click', () => setTheme(b.dataset.theme)));

  function updateToastGutter() {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.documentElement.style.setProperty('--toast-gutter', scrollbarWidth + 'px');
  }
  updateToastGutter();
  window.addEventListener('resize', updateToastGutter);
  window.addEventListener('orientationchange', updateToastGutter);

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
