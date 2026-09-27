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
      instructions_btn: 'View instructions', instructions_close: 'Close instructions', instructions_title: 'How to use',
      instructions_sections: {
        cloud: { main: 'File manager', markdown: 'Markdown editor', text: 'Text editor', image: 'Image editor', zip: 'Archive viewer', pdf: 'PDF viewer' },
        steps: { main: 'Dashboard & calendar', stats: 'Statistics', info: 'App information' },
        spends: { main: 'Expenses overview', table: 'Expenses table', settings: 'Stores & settings', analytics: 'Statistics & charts' },
        lopaka: { main: 'Editor & code generator' },
        portal: { home: 'Landing page', main: 'App dashboard', login: 'Sign in', register: 'Registration' },
        films: { main: 'Movie collection', add: 'Adding a movie', settings: 'Settings & management' },
        kanboard: { main: 'Dashboard', board: 'Kanban board', task: 'Task view' },
        recipes: { main: 'Recipe book', products: 'Product management', search: 'Recipe search' },
        smart_home: { main: 'Device dashboard', device: 'Device controls' }
      },
      instructions_texts: {
        cloud: {
          main: 'The file manager is the heart of Cloud Storage. The left panel shows a tree of folders — click a folder to open it, use the context menu (right-click) or the toolbar buttons to create folders and upload files via drag & drop. Double-clicking a file opens it in a suitable built-in editor. Files can be moved (drag & drop), renamed, copied, shared by link and deleted. The top bar shows your quota and lets you search across all files.',
          markdown: 'The Markdown editor opens .md files. The left half is a text editor with syntax highlighting, the right half is a live preview of the rendered document. The toolbar inserts headings, lists, links, tables, code blocks and images. Changes are saved automatically; use File → Export to download the result as HTML or PDF.',
          text: 'The text editor opens plain-text and code files (js, py, css, json, ini and more). It features Monaco editor with syntax highlighting, auto-indent, search and replace (Ctrl+F / Ctrl+H), and multiple tabs — you can keep several files open at once. Everything is saved back to your cloud storage.',
          image: 'The image editor opens photos and pictures. You can crop, rotate and flip, adjust brightness, contrast and saturation, apply filters, add text and shapes, and resize. Undo/redo is supported at every step. When you are done, save the result as PNG or JPEG back to your storage.',
          zip: 'The archive viewer opens .zip files without downloading them. Browse the archive as a folder tree, view files inside, and extract individual files or the whole archive into any folder of your storage. You can also create a new .zip from selected files and folders.',
          pdf: 'The PDF viewer opens .pdf documents with page thumbnails, zoom, full-text search and bookmarks navigation. You can also annotate and print. PDF files created from other editors (e.g. Markdown export) open here automatically.'
        },
        steps: {
          main: 'The dashboard shows today\'s steps, distance, calories and your daily goal progress ring. The calendar below displays history for the whole month — days are colored by activity level, click any day to see or edit its details. Use the “+” button to add steps manually, e.g. when you come back from a walk without your phone.',
          stats: 'Statistics aggregates your activity: yearly distribution of steps, monthly intensity heatmap and trends over weeks and months. Use it to spot progress, compare periods and check whether you reach your goals consistently. Charts can be switched between steps, distance and calories.',
          info: 'The info section explains how to pair the companion Android app: scan the QR code to connect your phone, after which steps sync automatically in the background. Here you can also configure notifications, background sync interval and data export.'
        },
        spends: {
          main: 'The main view is your spending dashboard: current month total, comparison with the previous month, a breakdown by stores and categories, and a chart of spending dynamics. Add a new expense with the “+” button: pick the store, amount, date and optional photo of the receipt.',
          table: 'The expenses table is a spreadsheet-like list of all purchases. Filter by month, store or category, sort by any column, edit cells inline and delete wrong entries. The table is the fastest way to review and fix many records at once.',
          settings: 'Settings manage stores (with logos and categories), currency and exchange rates, monthly budget limits, receipt photo OCR (automatic amount recognition) and CSV import/export for backups or migration from other trackers.',
          analytics: 'Statistics & charts shows spending by month, quarter and year, category pie charts, per-store trends and average check. Use it to find where the money goes and how your habits change over time.'
        },
        lopaka: {
          main: 'Lopaka is a graphics editor and code generator for embedded displays. Choose a platform library (TFT_eSPI, U8g2, LovyanGFX, Adafruit GFX, ESPHome, Flipper and others) and a display resolution, then draw with the toolbar: Select (V), Image (I), Paint brush (B), Text (T), Rectangle (R), Ellipse (O), Line (L), Triangle and Polygon. Each shape becomes a layer — move, resize and align it in the inspector on the right, or drag it directly on the canvas. The code panel at the bottom generates ready-to-paste firmware code (C/C++, MicroPython, ESPHome YAML) for every drawing operation; tweak code settings (wrapper function, fonts, variables) on the right and press COPY to copy the code or SAVE to keep the project. Shortcuts: Ctrl+Z undo, Ctrl+V paste import code, Del deletes the selected layer, arrows move by 1 px.'
        },
        portal: {
          home: 'The landing page is the public entry point of the platform: it describes the available applications, shows the latest news and lets you switch language (6 languages) and color theme. From here you can sign in, register or open the one-time PIN login.',
          main: 'The app dashboard (Shell Application) is your personal hub after sign-in. All installed applications appear as tiles — click one to open it in the workspace. The top bar gives you notifications, support chat and the side menu; the menu lists quick navigation (home, news, features) and profile/session actions.',
          login: 'The sign-in page supports Google OAuth and classic email/password. After several failed attempts a one-time PIN (OTP) by email can be required as extra protection. Sessions are device-tracked — you can review and terminate them in your profile.',
          register: 'Registration creates a free account: enter your email, name and password, confirm by the code sent to your mailbox, and you get access to all built-in applications with your personal data isolated per user.'
        },
        films: {
          main: 'The movie collection shows your library as cards with posters, ratings and categories. Use the search and filters to quickly find a title; open a movie to see details, comments and the watch button. The side menu (hamburger icon) contains navigation: collection, add movie, settings.',
          add: 'The “Add New Movie” dialog creates a record: enter the title, source language, year, content type (online link or local file), the link itself, category and star rating. The movie immediately appears in the collection and can be played with the built-in player.',
          settings: 'Settings (admin) manage the whole library: the Manage Movies tab lists all titles with visibility toggles and deletion, Manage Comments moderates user comments, and the Settings tab holds player and storage preferences.'
        },
        kanboard: {
          main: 'The dashboard is the entry point of Kanboard: it shows your projects with counters of tasks by column, “My tasks” and “My subtasks” lists, and the activity stream. Use “New project” to create a board, or the switch-project dropdown to jump between projects.',
          board: 'The Kanban board visualizes a project as columns (Backlog, Todo, Work in progress, Done…). Drag & drop cards between columns to change their status, use the “+” in a column to add a task, the search bar to filter by status/assignee/tag, and the Overview/List tabs to switch views.',
          task: 'The task view gathers everything about a single task: summary (status, priority, assignee, dates), description, sub-tasks with progress, internal/external links, attachments and the comment thread. The left sidebar offers actions: edit, recurrence, sub-tasks, links, comments, duplicate, move to another project and time tracking.'
        },
        recipes: {
          main: 'The recipe book displays your recipes as cards with photos and tags. Create a recipe with ingredients (linked to your product list), step-by-step instructions and a photo. The toolbar buttons open product management, recipe search and settings; recipes can be shared by link.',
          products: 'Product management maintains your pantry database: add products with units and prices, edit or delete them, and use them as ingredients in recipes. When you cook a recipe, the used products can be deducted from the stock automatically.',
          search: 'Recipe search finds dishes by name, tags or ingredients you currently have. Combine filters to answer “what can I cook from what is in the fridge” — matching recipes are scored by the number of available ingredients.'
        },
        smart_home: {
          main: 'The device dashboard shows all smart home devices as cards with live status: online/offline, room, current value and a quick toggle. Devices are grouped by rooms; the header icons switch between grid/table mode and open global settings (MQTT broker, integrations).',
          device: 'Clicking a device card opens its control panel: status badge (online/offline), room and type tags, the Controls section with switches/sliders (power, auto-off timer, brightness…), live parameters (power draw, temperature) and the Edit button to change the device configuration.'
        }
      },
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
      instructions_btn: 'Переглянути інструкцію', instructions_close: 'Закрити інструкцію', instructions_title: 'Як користуватися',
      instructions_sections: {
        cloud: { main: 'Менеджер файлів', markdown: 'Markdown-редактор', text: 'Текстовий редактор', image: 'Фоторедактор', zip: 'Перегляд архівів', pdf: 'Перегляд PDF' },
        steps: { main: 'Панель і календар', stats: 'Статистика', info: 'Інформація про застосунок' },
        spends: { main: 'Огляд витрат', table: 'Таблиця витрат', settings: 'Магазини та налаштування', analytics: 'Статистика та графіки' },
        lopaka: { main: 'Редактор і генератор коду' },
        portal: { home: 'Головна сторінка', main: 'Панель застосунків', login: 'Вхід', register: 'Реєстрація' },
        films: { main: 'Колекція фільмів', add: 'Додавання фільму', settings: 'Налаштування й керування' },
        kanboard: { main: 'Дашборд', board: 'Kanban-дошка', task: 'Перегляд задачі' },
        recipes: { main: 'Книга рецептів', products: 'Керування продуктами', search: 'Пошук рецептів' },
        smart_home: { main: 'Панель пристроїв', device: 'Керування пристроєм' }
      },
      instructions_texts: {
        cloud: {
          main: 'Менеджер файлів — серце хмарного сховища. Ліва панель показує дерево папок: клацніть папку, щоб відкрити, використовуйте контекстне меню (права кнопка) або кнопки панелі, щоб створювати папки та завантажувати файли перетягуванням. Подвійний клік відкриває файл у відповідному вбудованому редакторі. Файли можна переміщувати, перейменовувати, копіювати, ділитися посиланням і видаляти. Верхня панель показує квоту та дає змогу шукати по всіх файлах.',
          markdown: 'Markdown-редактор відкриває .md файли. Ліва половина — текстовий редактор із підсвіткою синтаксису, права — живий перегляд готового документа. Панель інструментів вставляє заголовки, списки, посилання, таблиці, блоки коду та зображення. Зміни зберігаються автоматично; через Файл → Експорт можна завантажити результат у HTML або PDF.',
          text: 'Текстовий редактор відкриває звичайні текстові файли та код (js, py, css, json, ini тощо). Це редактор Monaco з підсвіткою синтаксису, автовідступами, пошуком і заміною (Ctrl+F / Ctrl+H) та кількома вкладками — можна тримати відкритими одразу кілька файлів. Усе зберігається назад у хмарне сховище.',
          image: 'Фоторедактор відкриває фото та зображення. можна обрізати, обертати й віддзеркалювати, регулювати яскравість, контраст і насиченість, застосовувати фільтри, додавати текст і фігури, змінювати розмір. На кожному кроці доступні скасування/повторення. Готовий результат зберігається у PNG або JPEG назад у сховище.',
          zip: 'Переглядач архівів відкриває .zip файли без завантаження. Перегляайте вміст архіву як дерево папок, дивіться файли всередині та розпакуйте окремі файли або весь архів у будь-яку папку сховища. Також можна створити новий .zip із вибраних файлів і папок.',
          pdf: 'Переглядач PDF відкриває .pdf документи з мініатюрами сторінок, масштабуванням, повнотекстовим пошуком і навігацією за закладками. Також доступні анотації та друк. PDF, створені в інших редакторах (наприклад, експорт із Markdown), відкриваються тут автоматично.'
        },
        steps: {
          main: 'Панель показує кроки за сьогодні, дистанцію, калорії та кільце прогресу до денної цілі. Календар нижче відображає історію за весь місяць — дні розфарбовані за рівнем активності, клацніть день, щоб переглянути чи змінити деталі. Кнопка «+» додає кроки вручну, наприклад після прогулянки без телефона.',
          stats: 'Статистика агрегує активність: розподіл кроків за роками, карту інтенсивності по місяцях і тренди за тижнями та місяцями. Використовуйте, щоб бачити прогрес, порівнювати періоди та перевіряти, чи послідовно досягаєте цілей. Графіки перемикаються між кроками, дистанцією та калоріями.',
          info: 'Розділ «Інформація» пояснює, як підключити Android-застосунок: відскануйте QR-код, щоб з\'єднати телефон, після чого кроки синхронізуються автоматично у фоні. Тут також можна налаштувати повідомлення, інтервал фонової синхронізації та експорт даних.'
        },
        spends: {
          main: 'Головний вигляд — панель витрат: сума за поточний місяць, порівняння з попереднім, розбивка за магазинами й категоріями та графік динаміки. Додайте витрату кнопкою «+»: виберіть магазин, суму, дату та за бажанням фото чека.',
          table: 'Таблиця витрат — список усіх покупок у вигляді електронної таблиці. Фільтруйте за місяцем, магазином або категорією, сортуйте за будь-яким стовпцем, редагуйте клітинки на місці та видаляйте помилкові записи. Це найшвидший спосіб переглянути й виправити одразу багато записів.',
          settings: 'Налаштування керують магазинами (з логотипами й категоріями), валютою та курсами, місячними лімітами бюджету, OCR фото чеків (автоматичне розпізнавання сум) та імпортом/експортом CSV для бекапів або міграції з інших трекерів.',
          analytics: 'Статистика й графіки показує витрати за місяць, квартал і рік, кругові діаграми за категоріями, тренди по магазинах і середній чек. Використовуйте, щоб зрозуміти, куди йдуть гроші, і як змінюються звички.'
        },
        lopaka: {
          main: 'Lopaka — графічний редактор і генератор коду для вбудованих дисплеїв. Виберіть бібліотеку платформи (TFT_eSPI, U8g2, LovyanGFX, Adafruit GFX, ESPHome, Flipper та ін.) і роздільну здатність дисплея, малюйте панеллю інструментів: Вибір (V), Зображення (I), Пензель (B), Текст (T), Прямокутник (R), Еліпс (O), Лінія (L), Трикутник і Полігон. Кожна фігура стає шаром — переміщуйте, змінюйте розмір і вирівнюйте в інспекторі праворуч або перетягуйте прямо на канвасі. Панель коду внизу генерує готовий код прошивки (C/C++, MicroPython, ESPHome YAML) для кожної операції; налаштуйте параметри коду (функцію-обгортку, шрифти, змінні) праворуч і натисніть COPY, щоб скопіювати код, або SAVE, щоб зберегти проєкт. Гарячі клавіші: Ctrl+Z скасувати, Ctrl+V вставити код імпорту, Del видаляє вибраний шар, стрілки — зсув на 1 px.'
        },
        portal: {
          home: 'Головна сторінка — публічна точка входу платформи: описує доступні застосунки, показує останні новини, дозволяє вибрати мову (6 мов) і колірну тему. Звідси можна увійти, зареєструватися або відкрити вхід за одноразовим PIN-кодом.',
          main: 'Панель застосунків (Shell Application) — ваш особистий хаб після входу. Усі встановлені застосунки показані плитками — клацніть, щоб відкрити в робочій області. Верхня панель дає повідомлення, чат підтримки та бічне меню; у меню — швидка навігація (головна, новини, можливості) і дії профілю/сесій.',
          login: 'Сторінка входу підтримує Google OAuth і класичний вхід за email/паролем. Після кількох невдалих спроб може знадобитися одноразовий PIN (OTP) з листа як додатковий захист. Сесії відстежуються за пристроями — їх можна переглянути й завершити у профілі.',
          register: 'Реєстрація створює безкоштовний акаунт: введіть email, ім\'я та пароль, підтвердьте кодом з листа — і ви отримуєте доступ до всіх вбудованих застосунків з персональною ізоляцією даних кожного користувача.'
        },
        films: {
          main: 'Колекція фільмів показує вашу бібліотеку картками з постерами, рейтингами й категоріями. Пошук і фільтри допоможуть швидко знайти фільм; відкрийте картку, щоб побачити деталі, коментарі та кнопку перегляду. Бічне меню (іконка-гамбургер) містить навігацію: колекція, додати фільм, налаштування.',
          add: 'Діалог «Додати новий фільм» створює запис: введіть назву, мову оригіналу, рік, тип контенту (онлайн-посилання або локальний файл), саме посилання, категорію та зірковий рейтинг. Фільм одразу з\'являється в колекції та відтворюється вбудованим плеєром.',
          settings: 'Налаштування (для адміністратора) керують усією бібліотекою: вкладка «Керування фільмами» показує всі назви з перемикачами видимості та видаленням, «Керування коментарями» модерує коментарі користувачів, а вкладка «Налаштування» зберігає параметри плеєра та сховища.'
        },
        kanboard: {
          main: 'Дашборд — вхідна точка Kanboard: показує ваші проєкти з лічильниками задач за колонками, списки «Мої задачі» та «Мої підзадачі», стрічку активності. Використовуйте «Новий проєкт», щоб створити дошку, або випадний список проєктів для переключення.',
          board: 'Kanban-дошка представляє проєкт колонками (Backlog, Todo, In progress, Done…). Перетягуйте картки між колонками, щоб змінювати статус, «+» у колонці додає задачу, рядок пошуку фільтрує за статусом/виконавцем/тегом, вкладки Overview/List перемикають вид.',
          task: 'Перегляд задачі збирає все про задачу: зведення (статус, пріоритет, виконавець, дати), опис, підзадачі з прогресом, внутрішні/зовнішні посилання, вкладення та нитку коментарів. Ліва панель пропонує дії: редагувати, повторюваність, підзадачі, посилання, коментарі, дублювання, перенесення в інший проєкт і облік часу.'
        },
        recipes: {
          main: 'Книга рецептів показує рецепти картками з фото й тегами. Створіть рецепт з інгредієнтами (пов\'язаними зі списком продуктів), покроковими інструкціями та фото. Кнопки панелі відкривають керування продуктами, пошук рецептів і налаштування; рецептами можна ділитися посиланням.',
          products: 'Керування продуктами веде базу вашої комори: додавайте продукти з одиницями й цінами, редагуйте та видаляйте їх, використовуйте як інгредієнти в рецептах. При готуванні рецепта використані продукти можуть списуватися зі складу автоматично.',
          search: 'Пошук рецептів знаходить страви за назвою, тегами або наявними інгредієнтами. Комбінуйте фільтри, щоб відповісти на питання «що приготувати з того, що є в холодильнику» — відповідні рецепти ранжуються за кількістю доступних інгредієнтів.'
        },
        smart_home: {
          main: 'Панель пристроїв показує всі пристрої розумного дому картками зі статусом у реальному часі: онлайн/офлайн, кімната, поточне значення та швидкий перемикач. Пристрої групуються за кімнатами; іконки в шапці перемикають режим сітка/таблиця та відкривають загальні налаштування (MQTT-брокер, інтеграції).',
          device: 'Клік по картці пристрою відкриває панель керування: бейдж статусу (онлайн/офлайн), теги кімнати й типу, секція «Керування» з перемикачами/слайдерами (живлення, таймер автовимкнення, яскравість…), живі параметри (споживання, температура) та кнопка «Редагувати» для зміни конфігурації пристрою.'
        }
      },
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
      instructions_btn: 'Посмотреть инструкцию', instructions_close: 'Закрыть инструкцию', instructions_title: 'Как пользоваться',
      instructions_sections: {
        cloud: { main: 'Файловый менеджер', markdown: 'Markdown-редактор', text: 'Текстовый редактор', image: 'Фоторедактор', zip: 'Просмотр архивов', pdf: 'Просмотр PDF' },
        steps: { main: 'Панель и календарь', stats: 'Статистика', info: 'Информация о приложении' },
        spends: { main: 'Обзор расходов', table: 'Таблица расходов', settings: 'Магазины и настройки', analytics: 'Статистика и графики' },
        lopaka: { main: 'Редактор и генератор кода' },
        portal: { home: 'Главная страница', main: 'Панель приложений', login: 'Вход', register: 'Регистрация' },
        films: { main: 'Коллекция фильмов', add: 'Добавление фильма', settings: 'Настройки и управление' },
        kanboard: { main: 'Дашборд', board: 'Kanban-доска', task: 'Просмотр задачи' },
        recipes: { main: 'Книга рецептов', products: 'Управление продуктами', search: 'Поиск рецептов' },
        smart_home: { main: 'Панель устройств', device: 'Управление устройством' }
      },
      instructions_texts: {
        cloud: {
          main: 'Файловый менеджер — сердце облачного хранилища. Левая панель показывает дерево папок: щёлкните папку, чтобы открыть, используйте контекстное меню (правая кнопка) или кнопки панели, чтобы создавать папки и загружать файлы перетаскиванием. Двойной клик открывает файл в подходящем встроенном редакторе. Файлы можно перемещать, переименовывать, копировать, делиться ссылкой и удалять. Верхняя панель показывает квоту и позволяет искать по всем файлам.',
          markdown: 'Markdown-редактор открывает .md файлы. Левая половина — текстовый редактор с подсветкой синтаксиса, правая — живой предпросмотр готового документа. Панель инструментов вставляет заголовки, списки, ссылки, таблицы, блоки кода и изображения. Изменения сохраняются автоматически; через Файл → Экспорт можно скачать результат в HTML или PDF.',
          text: 'Текстовый редактор открывает обычные текстовые файлы и код (js, py, css, json, ini и др.). Это редактор Monaco с подсветкой синтаксиса, автоотступами, поиском и заменой (Ctrl+F / Ctrl+H) и несколькими вкладками — можно держать открытыми сразу несколько файлов. Всё сохраняется обратно в облачное хранилище.',
          image: 'Фоторедактор открывает фото и картинки. Можно обрезать, поворачивать и отражать, регулировать яркость, контраст и насыщенность, применять фильтры, добавлять текст и фигуры, менять размер. На каждом шаге доступны отмена/повтор. Готовый результат сохраняется в PNG или JPEG обратно в хранилище.',
          zip: 'Просмотрщик архивов открывает .zip файлы без скачивания. Просматривайте содержимое архива как дерево папок, смотрите файлы внутри и распаковывайте отдельные файлы или весь архив в любую папку хранилища. Также можно создать новый .zip из выбранных файлов и папок.',
          pdf: 'Просмотрщик PDF открывает .pdf документы с миниатюрами страниц, масштабированием, полнотекстовым поиском и навигацией по закладкам. Также доступны аннотации и печать. PDF, созданные в других редакторах (например, экспорт из Markdown), открываются здесь автоматически.'
        },
        steps: {
          main: 'Панель показывает шаги за сегодня, дистанцию, калории и кольцо прогресса к дневной цели. Календарь ниже отображает историю за весь месяц — дни раскрашены по уровню активности, щёлкните день, чтобы посмотреть или изменить детали. Кнопка «+» добавляет шаги вручную, например после прогулки без телефона.',
          stats: 'Статистика агрегирует активность: распределение шагов по годам, карту интенсивности по месяцам и тренды за недели и месяцы. Используйте, чтобы видеть прогресс, сравнивать периоды и проверять, последовательно ли достигаете целей. Графики переключаются между шагами, дистанцией и калориями.',
          info: 'Раздел «Информация» объясняет, как подключить Android-приложение: отсканируйте QR-код, чтобы соединить телефон, после чего шаги синхронизируются автоматически в фоне. Здесь же можно настроить уведомления, интервал фоновой синхронизации и экспорт данных.'
        },
        spends: {
          main: 'Главный вид — панель расходов: сумма за текущий месяц, сравнение с предыдущим, разбивка по магазинам и категориям и график динамики. Добавьте расход кнопкой «+»: выберите магазин, сумму, дату и по желанию фото чека.',
          table: 'Таблица расходов — список всех покупок в виде электронной таблицы. Фильтруйте по месяцу, магазину или категории, сортируйте по любому столбцу, редактируйте ячейки на месте и удаляйте ошибочные записи. Это самый быстрый способ просмотреть и исправить сразу много записей.',
          settings: 'Настройки управляют магазинами (с логотипами и категориями), валютой и курсами, месячными лимитами бюджета, OCR фото чеков (автоматическое распознавание сумм) и импортом/экспортом CSV для бекапов или миграции с других трекеров.',
          analytics: 'Статистика и графики показывает расходы за месяц, квартал и год, круговые диаграммы по категориям, тренды по магазинам и средний чек. Используйте, чтобы понять, куда уходят деньги, и как меняются привычки.'
        },
        lopaka: {
          main: 'Lopaka — графический редактор и генератор кода для встраиваемых дисплеев. Выберите библиотеку платформы (TFT_eSPI, U8g2, LovyanGFX, Adafruit GFX, ESPHome, Flipper и др.) и разрешение дисплея, рисуйте панелью инструментов: Выбор (V), Изображение (I), Кисть (B), Текст (T), Прямоугольник (R), Эллипс (O), Линия (L), Треугольник и Полигон. Каждая фигура становится слоем — перемещайте, меняйте размер и выравнивайте в инспекторе справа или перетаскивайте прямо на канвасе. Панель кода внизу генерирует готовый код прошивки (C/C++, MicroPython, ESPHome YAML) для каждой операции; настройте параметры кода (функцию-обёртку, шрифты, переменные) справа и нажмите COPY, чтобы скопировать код, или SAVE, чтобы сохранить проект. Горячие клавиши: Ctrl+Z отменить, Ctrl+V вставить код импорта, Del удаляет выбранный слой, стрелки — сдвиг на 1 px.'
        },
        portal: {
          home: 'Главная страница — публичная точка входа платформы: описывает доступные приложения, показывает последние новости, позволяет выбрать язык (6 языков) и цветовую тему. Отсюда можно войти, зарегистрироваться или открыть вход по одноразовому PIN-коду.',
          main: 'Панель приложений (Shell Application) — ваш личный хаб после входа. Все установленные приложения показаны плитками — щёлкните, чтобы открыть в рабочей области. Верхняя панель даёт уведомления, чат поддержки и боковое меню; в меню — быстрая навигация (главная, новости, возможности) и действия профиля/сессий.',
          login: 'Страница входа поддерживает Google OAuth и классический вход по email/паролю. После нескольких неудачных попыток может потребоваться одноразовый PIN (OTP) из письма как дополнительная защита. Сессии отслеживаются по устройствам — их можно просмотреть и завершить в профиле.',
          register: 'Регистрация создаёт бесплатный аккаунт: введите email, имя и пароль, подтвердите кодом из письма — и вы получаете доступ ко всем встроенным приложениям с персональной изоляцией данных каждого пользователя.'
        },
        films: {
          main: 'Коллекция фильмов показывает вашу библиотеку карточками с постерами, рейтингами и категориями. Поиск и фильтры помогут быстро найти фильм; откройте карточку, чтобы увидеть детали, комментарии и кнопку просмотра. Боковое меню (иконка-гамбургер) содержит навигацию: коллекция, добавить фильм, настройки.',
          add: 'Диалог «Добавить новый фильм» создаёт запись: введите название, исходный язык, год, тип контента (онлайн-ссылка или локальный файл), саму ссылку, категорию и звёздный рейтинг. Фильм сразу появляется в коллекции и воспроизводится встроенным плеером.',
          settings: 'Настройки (для администратора) управляют всей библиотекой: вкладка «Управление фильмами» показывает все названия с переключателями видимости и удалением, «Управление комментариями» модерирует комментарии пользователей, а вкладка «Настройки» хранит параметры плеера и хранилища.'
        },
        kanboard: {
          main: 'Дашборд — входная точка Kanboard: показывает ваши проекты со счётчиками задач по колонкам, списки «Мои задачи» и «Мои подзадачи», ленту активности. Используйте «Новый проект», чтобы создать доску, или выпадающий список проектов для переключения.',
          board: 'Kanban-доска представляет проект колонками (Backlog, Todo, In progress, Done…). Перетаскивайте карточки между колонками, чтобы менять статус, «+» в колонке добавляет задачу, строка поиска фильтрует по статусу/исполнителю/тегу, вкладки Overview/List переключают вид.',
          task: 'Просмотр задачи собирает всё о задаче: сводка (статус, приоритет, исполнитель, даты), описание, подзадачи с прогрессом, внутренние/внешние ссылки, вложения и цепочка комментариев. Левая панель предлагает действия: редактировать, повторяемость, подзадачи, ссылки, комментарии, дублирование, перенос в другой проект и учёт времени.'
        },
        recipes: {
          main: 'Книга рецептов показывает рецепты карточками с фото и тегами. Создайте рецепт с ингредиентами (связанными со списком продуктов), пошаговыми инструкциями и фото. Кнопки панели открывают управление продуктами, поиск рецептов и настройки; рецептами можно делиться ссылкой.',
          products: 'Управление продуктами ведёт базу вашей кладовой: добавляйте продукты с единицами и ценами, редактируйте и удаляйте их, используйте как ингредиенты в рецептах. При приготовлении рецепта использованные продукты могут списываться со склада автоматически.',
          search: 'Поиск рецептов находит блюда по названию, тегам или имеющимся ингредиентам. Комбинируйте фильтры, чтобы ответить на вопрос «что приготовить из того, что есть в холодильнике» — подходящие рецепты ранжируются по числу доступных ингредиентов.'
        },
        smart_home: {
          main: 'Панель устройств показывает все устройства умного дома карточками со статусом в реальном времени: онлайн/офлайн, комната, текущее значение и быстрый переключатель. Устройства группируются по комнатам; иконки в шапке переключают режим сетка/таблица и открывают общие настройки (MQTT-брокер, интеграции).',
          device: 'Клик по карточке устройства открывает панель управления: бейдж статуса (онлайн/офлайн), теги комнаты и типа, секция «Управление» с переключателями/слайдерами (питание, таймер автоотключения, яркость…), живые параметры (потребление, температура) и кнопка «Редактировать» для изменения конфигурации устройства.'
        }
      },
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
      instructions_btn: 'Паглядзець інструкцыю', instructions_close: 'Закрыць інструкцыю', instructions_title: 'Як карыстацца',
      instructions_sections: {
        cloud: { main: 'Менеджар файлаў', markdown: 'Markdown-рэдактар', text: 'Тэкставы рэдактар', image: 'Фоторэдактар', zip: 'Прагляд архіваў', pdf: 'Прагляд PDF' },
        steps: { main: 'Панэль і каляндар', stats: 'Статыстыка', info: 'Інфармацыя пра прыкладанне' },
        spends: { main: 'Агляд выдаткаў', table: 'Табліца выдаткаў', settings: 'Крамы і налады', analytics: 'Статыстыка і графікі' },
        lopaka: { main: 'Рэдактар і генератар кода' },
        portal: { home: 'Галоўная старонка', main: 'Панэль прыкладанняў', login: 'Уваход', register: 'Рэгістрацыя' },
        films: { main: 'Калекцыя фільмаў', add: 'Даданне фільма', settings: 'Налады і кіраванне' },
        kanboard: { main: 'Дашборд', board: 'Kanban-дошка', task: 'Прагляд задачы' },
        recipes: { main: 'Кніга рэцэптаў', products: 'Кіраванне прадуктамі', search: 'Пошук рэцэптаў' },
        smart_home: { main: 'Панэль прылад', device: 'Кіраванне прыладай' }
      },
      instructions_texts: {
        cloud: {
          main: 'Менеджар файлаў — срэбца воблачнага сховішча. Левая панэль паказвае дрэва папак: клікніце папку, каб адкрыць, выкарыстоўвайце кантэкстнае меню (правая кнопка) або кнопкі панэлі, каб ствараць папкі і загружаць файлы перацягваннем. Падвойны клік адкрывае файл у адпаведным убудаваным рэдактары. Файлы можна перамяшчаць, перайменаваць, капіяваць, дзяліцца спасылкай і выдаляць. Верхняя панэль паказвае квоту і дазваляе шукаць па ўсіх файлах.',
          markdown: 'Markdown-рэдактар адкрывае .md файлы. Левая палова — тэкставы рэдактар з падсветкай сінтаксісу, правая — жывы прагляд гатоваага дакумента. Панэль інструментаў устаўляе загалоўкі, спісы, спасылкі, табліцы, блокі коду і малюнкі. Змены захоўваюцца аўтаматычна; праз Файл → Экспарт можна спампаваць вынік у HTML ці PDF.',
          text: 'Тэкставы рэдактар адкрывае звычайныя тэкставыя файлы і код (js, py, css, json, ini і інш.). Гэта рэдактар Monaco з падсветкай сінтаксісу, аўтаадступамі, пошукам і заменай (Ctrl+F / Ctrl+H) і некалькімі ўкладкамі. Усё захоўваецца назад у воблачнае сховішча.',
          image: 'Фоторэдактар адкрывае фота і малюнкі. Можна абразаць, паварочваць і адлюстроўваць, рэгуляваць яркасць, кантраст і насошенасць, прымяняць фільтры, дадаваць тэкст і фігуры, змяняць памер. Гатовы вынік захоўваецца ў PNG або JPEG назад у сховішча.',
          zip: 'Прагляд архіваў адкрывае .zip файлы без спампоўвання. Праглядайце змесціва архіва як дрэва папак, глядзіце файлы ўнутры і распакоўвайце асобныя файлы або ўвесь архіў у любую папку сховішча. Таксама можна стварыць новы .zip з выбраных файлаў і папак.',
          pdf: 'Прагляд PDF адкрывае .pdf дакументы з мініяцюрамі старонак, маштабаваннем, паўнатэкставым пошукам і навігацыяй па закладках. Таксама даступныя анатацыі і друкарства.'
        },
        steps: {
          main: 'Панэль паказвае крокі за сёння, адлегласць, калорыі і кольца прагрэсу да дзеннай мэты. Каляндар ніжэй адлюстроўвае гісторыю за ўвесь месяц — дні расфарбаваны па ўзроўні актыўнасці. Кнопка «+» дадае крокі ўручную.',
          stats: 'Статыстыка агрэгуе актыўнасць: размеркаванне крокаў па гадах, карту інтэнсіўнасці па месяцах і трэнды за тыдні і месяцы. Графікі пераключаюцца паміж крокамі, адлегласцю і калорыямі.',
          info: 'Раздзел «Інфармацыя» тлумачыць, як падключыць Android-прыкладанне: адсканіруйце QR-код, каб злучыць тэлефон, пасля чаго крокі сінхранізуюцца аўтаматычна ў фонe. Тут можна наладзіць паведамленні і экспарт даных.'
        },
        spends: {
          main: 'Галоўны выгляд — панэль выдаткаў: сума за бягучы месяц, параўнанне з папярэднім, разбіўка па крамах і катэгорыях і графік дынамікі. Дадайце выдатак кнопкай «+»: выберыце краму, суму, дату і пры жаданні фота чэка.',
          table: 'Табліца выдаткаў — спіс усіх пакупак у выглядзе электроннай табліцы. Фільтруйце па месяцы, краме або катэгорыі, сартуйце па любым слупку, рэдагуйце ячэйкі на месцы і выдаляйце памылковыя запісы.',
          settings: 'Налады кіруюць крамамі (з лагатыпамі і катэгорыямі), валютай і курсамі, месяцовымі лімітамі бюджэту, OCR фота чэкаў і імпартам/экспартам CSV для бэкапаў або міграцыі.',
          analytics: 'Статыстыка і графікі паказвае выдаткі за месяц, квартал і год, кругавыя дыяграмы па катэгорыях, трэнды па крамах і сярэдні чэк.'
        },
        lopaka: {
          main: 'Lopaka — графічны рэдактар і генератар кода для ўбудаваных дысплеяў. Выберыце бібліятэку платформы (TFT_eSPI, U8g2, LovyanGFX, Adafruit GFX, ESPHome, Flipper і інш.) і разрозненне дысплея, маляйце панэллю інструментаў: Выбар (V), Малюнак (I), Пэндзаль (B), Тэкст (T), Прамавугольнік (R), Эліпс (O), Лінія (L), Трохвугольнік і Полігон. Кожная фігура становіцца пластом — перамяшчайце, змяняйце памер і выраўноўвайце ў інспектары справа. Панэль кода ўнізе генеруе гатовы код прашыўкі (C/C++, MicroPython, ESPHome YAML); націсніце COPY, каб скапіяваць код, або SAVE, каб захаваць праект. Гарачыя клавішы: Ctrl+Z адмяніць, Del выдаляе выбраны пласт, стрэлкі — зрух на 1 px.'
        },
        portal: {
          home: 'Галоўная старонка — публічная точка ўваходу платформы: апісвае даступныя прыкладанні, паказвае апошнія навіны, дазваляе выбраць мову (6 моў) і каляровую тэму. Адсюль можна ўвайсці, зарэгістравацца або адкрыць уваход па аднаразовым PIN-кодзе.',
          main: 'Панэль прыкладанняў (Shell Application) — ваш асабісты хаб пасля ўваходу. Усе ўстаноўленыя прыкладанні паказаны пліткамі — клікніце, каб адкрыць. Верхняя панэль дае паведамленні, чат падтрымкі і бакавое меню; у меню — хуткая навігацыя і дзеянні профілю/сесій.',
          login: 'Старонка ўваходу падтрымлівае Google OAuth і класічны ўваход па email/паролі. Пасля некалькіх няўдалых спроб можа запатрабавацца аднаразовы PIN (OTP) з ліста. Сесіі адсочваюцца па прыладах — іх можна праглядзець і завяршыць у профілі.',
          register: 'Рэгістрацыя стварае бясплатны ўліковы запіс: увядзіце email, імя і пароль, пацвердзіце кодам з ліста — і вы атрымліваеце доступ да ўсіх убудаваных прыкладанняў з персанальнай ізаляцыяй даных.'
        },
        films: {
          main: 'Калекцыя фільмаў паказвае вашу бібліятэку карткамі з постарами, рэйтынгамі і катэгорыямі. Пошук і фільтры дапамогуць хутка знайсці фільм; адкрыйце картку, каб убачыць дэталі, каментары і кнопку прагляду. Бакавое меню змяшчае навігацыю: калекцыя, дадаць фільм, налады.',
          add: 'Дыялог «Дадаць новы фільм» стварае запіс: увядзіце назву, мову арыгінала, год, тып кантэнту (онлайн-спасылка або лакальны файл), саму спасылку, катэгорыю і зорны рэйтынг. Фільм адразу з\'яўляецца ў калекцыі.',
          settings: 'Налады (для адміністратара) кіруюць усёй бібліятэкай: укладка «Кіраванне фільмамі» паказвае ўсе назвы з пераключальнікамі бачнасці і выдаленнем, «Кіраванне каментарамі» мадэруе каментары, а ўкладка «Налады» змяшчае параметры плеера і сховішча.'
        },
        kanboard: {
          main: 'Дашборд — уваходная точка Kanboard: паказвае вашы праекты з лічильнікамі задач па калонках, спісы «Мае задачы» і «Мае падзадачы», стужку актыўнасці. «Новы праект» стварае дошку.',
          board: 'Kanban-дошка ўяўляе праект калонкамі (Backlog, Todo, In progress, Done…). Перацягвайце карткі паміж калонкамі, «+» у калонцы дадае задачу, радок пошуку фільтруе па статусе/выканаўцу/тэгу.',
          task: 'Прагляд задачы збірае ўсё пра задачу: зводка (статус, прыярытэт, выканаўца, даты), апісанне, падзадачы з прагрэсам, спасылкі, укладанні і ланцуг каментараў. Левая панэль прапануе дзеянні: рэдагаваць, падзадачы, спасылкі, каментары, дубляванне, перанос у іншы праект.'
        },
        recipes: {
          main: 'Кніга рэцэптаў паказвае рэцэпты карткамі з фота і тэгамі. Стварыце рэцэпт з інгрэдыентамі (звязанымі са спісам прадуктаў), пакрокавымі інструкцыямі і фота. Кнопкі панэлі адкрываюць кіраванне прадуктамі, пошук рэцэптаў і налады; рэцэптамі можна дзяліцца спасылкай.',
          products: 'Кіраванне прадуктамі вядзе базу вашай кладоўкі: дадавайце прадукты з адзінкамі і цэнамі, рэдагуйце і выдаляйце іх, выкарыстоўвайце як інгрэдыенты ў рэцэптах. Пры гатаванні выкарыстаныя прадукты могуць спісвацца са складу аўтаматычна.',
          search: 'Пошук рэцэптаў знаходзіць стравы па назве, тэгах або наяўных інгрэдыентах. Камбінуйце фільтры, каб адказаць на пытанне «што прыгатаваць з таго, што ёсць у халадзільніку».'
        },
        smart_home: {
          main: 'Панэль прылад паказвае ўсе прылады розумнага дому карткамі са статусам у рэальным часе: онлайн/офлайн, пакой, бягучае значэнне і хуткі пераключальнік. Прылады групуюцца па пакоях; іконкі ў шапцы пераключаюць рэжым сетка/табліца і адкрываюць агульныя налады (MQTT-брокер, інтэграцыі).',
          device: 'Клік па картцы прылады адкрывае панэль кіравання: бэйдж статуса, тэгі пакоя і тыпу, секцыя «Кіраванне» з пераключальнікамі/слайдэрамі (сілкаванне, таймер аўтаадключэння, яркасць), жывыя параметры (спажыванне, тэмпература) і кнопка «Рэдагаваць».'
        }
      },
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
      instructions_btn: 'Zobacz instrukcję', instructions_close: 'Zamknij instrukcję', instructions_title: 'Jak korzystać',
      instructions_sections: {
        cloud: { main: 'Menedżer plików', markdown: 'Edytor Markdown', text: 'Edytor tekstu', image: 'Edytor zdjęć', zip: 'Przeglądarka archiwów', pdf: 'Przeglądarka PDF' },
        steps: { main: 'Panel i kalendarz', stats: 'Statystyki', info: 'Informacje o aplikacji' },
        spends: { main: 'Przegląd wydatków', table: 'Tabela wydatków', settings: 'Sklepy i ustawienia', analytics: 'Statystyki i wykresy' },
        lopaka: { main: 'Edytor i generator kodu' },
        portal: { home: 'Strona główna', main: 'Panel aplikacji', login: 'Logowanie', register: 'Rejestracja' },
        films: { main: 'Kolekcja filmów', add: 'Dodawanie filmu', settings: 'Ustawienia i zarządzanie' },
        kanboard: { main: 'Pulpit', board: 'Tablica Kanban', task: 'Widok zadania' },
        recipes: { main: 'Książka przepisów', products: 'Zarządzanie produktami', search: 'Wyszukiwanie przepisów' },
        smart_home: { main: 'Panel urządzeń', device: 'Sterowanie urządzeniem' }
      },
      instructions_texts: {
        cloud: {
          main: 'Menedżer plików to serce przechowywania w chmurze. Lewy panel pokazuje drzewo folderów — kliknij folder, aby otworzyć, użyj menu kontekstowego (prawy przycisk) lub przycisków paska, aby tworzyć foldery i przesyłać pliki przez przeciąganie. Podwójne kliknięcie otwiera plik w odpowiednim wbudowanym edytorze. Pliki można przenosić, zmieniać nazwy, kopiować, udostępniać linkiem i usuwać. Górny pasek pokazuje limit i umożliwia wyszukiwanie we wszystkich plikach.',
          markdown: 'Edytor Markdown otwiera pliki .md. Lewa połowa to edytor tekstu z podświetlaniem składni, prawa — podgląd gotowego dokumentu na żywo. Pasek narzędzi wstawia nagłówki, listy, linki, tabele, bloki kodu i obrazy. Zmiany zapisują się automatycznie; przez Plik → Eksport można pobrać wynik jako HTML lub PDF.',
          text: 'Edytor tekstu otwiera zwykłe pliki tekstowe i kod (js, py, css, json, ini i inne). To edytor Monaco z podświetlaniem składni, automatycznymi wcięciami, wyszukiwaniem i zamianą (Ctrl+F / Ctrl+H) oraz wieloma kartami. Wszystko zapisuje się z powrotem do przechowalni w chmurze.',
          image: 'Edytor zdjęć otwiera fotografie i obrazy. Można przycinać, obracać i odbijać, regulować jasność, kontrast i nasycenie, stosować filtry, dodawać tekst i kształty, zmieniać rozmiar. Gotowy wynik zapisuje się jako PNG lub JPEG z powrotem do przechowalni.',
          zip: 'Przeglądarka archiwów otwiera pliki .zip bez pobierania. Przeglądaj zawartość archiwum jako drzewo folderów, oglądaj pliki w środku i rozpakowuj pojedyncze pliki lub całe archiwum do dowolnego folderu. Można też utworzyć nowy .zip z wybranych plików i folderów.',
          pdf: 'Przeglądarka PDF otwiera dokumenty .pdf z miniaturami stron, powiększeniem, wyszukiwaniem pełnotekstowym i nawigacją po zakładkach. Dostępne są także adnotacje i druk.'
        },
        steps: {
          main: 'Panel pokazuje dzisiejsze kroki, dystans, kalorie i pierścień postępu do dziennego celu. Kalendarz poniżej wyświetla historię za cały miesiąc — dni są kolorowane według poziomu aktywności. Przycisk „+” dodaje kroki ręcznie, np. po spacerze bez telefonu.',
          stats: 'Statystyki agregują aktywność: rozkład kroków według lat, mapę intensywności według miesięcy i trendy tygodniowe oraz miesięczne. Wykresy przełączają się między krokami, dystansem i kaloriami.',
          info: 'Sekcja „Informacje” wyjaśnia, jak sparować aplikację na Androida: zeskanuj kod QR, aby połączyć telefon, po czym kroki synchronizują się automatycznie w tle. Tutaj można też skonfigurować powiadomienia i eksport danych.'
        },
        spends: {
          main: 'Widok główny to panel wydatków: suma za bieżący miesiąc, porównanie z poprzednim, podział według sklepów i kategorii oraz wykres dynamiki. Dodaj wydatek przyciskiem „+”: wybierz sklep, kwotę, datę i opcjonalnie zdjęcie paragonu.',
          table: 'Tabela wydatków to lista wszystkich zakupów w formie arkusza. Filtruj według miesiąca, sklepu lub kategorii, sortuj według dowolnej kolumny, edytuj komórki na miejscu i usuwaj błędne wpisy.',
          settings: 'Ustawienia zarządzają sklepami (z logo i kategoriami), walutą i kursami, miesięcznymi limitami budżetu, OCR zdjęć paragonów oraz importem/eksportem CSV do kopii zapasowych lub migracji.',
          analytics: 'Statystyki i wykresy pokazują wydatki za miesiąc, kwartał i rok, wykresy kołowe kategorii, trendy sklepów i średni paragon.'
        },
        lopaka: {
          main: 'Lopaka to edytor graficzny i generator kodu dla wyświetlaczy wbudowanych. Wybierz bibliotekę platformy (TFT_eSPI, U8g2, LovyanGFX, Adafruit GFX, ESPHome, Flipper itd.) i rozdzielczość wyświetlacza, rysuj paskiem narzędzi: Zaznaczenie (V), Obraz (I), Pędzel (B), Tekst (T), Prostokąt (R), Elipsa (O), Linia (L), Trójkąt i Wielokąt. Każdy kształt staje się warstwą — przesuwaj, zmieniaj rozmiar i wyrównuj w inspectorze po prawej. Panel kodu na dole generuje gotowy kod firmware (C/C++, MicroPython, ESPHome YAML); naciśnij COPY, aby skopiować kod, lub SAVE, aby zapisać projekt. Skróty: Ctrl+Z cofnij, Del usuwa zaznaczoną warstwę, strzałki — przesuw o 1 px.'
        },
        portal: {
          home: 'Strona główna to publiczny punkt wejścia platformy: opisuje dostępne aplikacje, pokazuje najnowsze wiadomości, pozwala wybrać język (6 języków) i motyw kolorystyczny. Stąd można się zalogować, zarejestrować lub otworzyć logowanie jednorazowym PIN-em.',
          main: 'Panel aplikacji (Shell Application) to osobisty hub po zalogowaniu. Wszystkie zainstalowane aplikacje widoczne są jako kafelki — kliknij, aby otworzyć. Górny pasek daje powiadomienia, czat wsparcia i menu boczne; w menu szybka nawigacja i akcje profilu/sesji.',
          login: 'Strona logowania obsługuje Google OAuth i klasyczne logowanie email/hasło. Po kilku nieudanych próbach może być wymagany jednorazowy PIN (OTP) z e-maila. Sesje są śledzone według urządzeń — można je przejrzeć i zakończyć w profilu.',
          register: 'Rejestracja tworzy darmowe konto: wpisz e-mail, imię i hasło, potwierdź kodem z wiadomości — i masz dostęp do wszystkich wbudowanych aplikacji z osobistą izolacją danych.'
        },
        films: {
          main: 'Kolekcja filmów pokazuje bibliotekę jako karty z plakatami, ocenami i kategoriami. Wyszukiwanie i filtry pomogą szybko znaleźć tytuł; otwórz kartę, aby zobaczyć szczegóły, komentarze i przycisk odtwarzania. Menu boczne zawiera nawigację: kolekcja, dodaj film, ustawienia.',
          add: 'Okno „Dodaj nowy film” tworzy wpis: podaj tytuł, język oryginalny, rok, typ zawartości (link online lub plik lokalny), sam link, kategorię i ocenę gwiazdkową. Film od razu pojawia się w kolekcji i jest odtwarzany wbudowanym odtwarzaczem.',
          settings: 'Ustawienia (dla administratora) zarządzają całą biblioteką: zakładka „Zarządzanie filmami” pokazuje wszystkie tytuły z przełącznikami widoczności i usuwania, „Zarządzanie komentarzami” moderuje komentarze, a zakładka „Ustawienia” przechowuje preferencje odtwarzacza i magazynu.'
        },
        kanboard: {
          main: 'Pulpit to punkt wejścia Kanboard: pokazuje projekty z licznikami zadań według kolumn, listy „Moje zadania” i „Moje podzadania” oraz strumień aktywności. „Nowy projekt” tworzy tablicę.',
          board: 'Tablica Kanban przedstawia projekt jako kolumny (Backlog, Todo, W toku, Gotowe…). Przeciągaj karty między kolumnami, aby zmieniać status, „+” w kolumnie dodaje zadanie, pasek wyszukiwania filtruje według statusu/wykonawcy/etykiety.',
          task: 'Widok zadania zbiera wszystko o zadaniu: podsumowanie (status, priorytet, wykonawca, daty), opis, podzadania z postępem, linki wewnętrzne/zewnętrzne, załączniki i wątek komentarzy. Lewy panel oferuje akcje: edycja, powtarzalność, podzadania, linki, komentarze, duplikowanie, przeniesienie do innego projektu.'
        },
        recipes: {
          main: 'Książka przepisów pokazuje przepisy jako karty ze zdjęciami i tagami. Utwórz przepis ze składnikami (powiązanymi z listą produktów), instrukcjami krok po kroku i zdjęciem. Przyciski paska otwierają zarządzanie produktami, wyszukiwanie przepisów i ustawienia; przepisami można udostępniać linkiem.',
          products: 'Zarządzanie produktami prowadzi bazę Twojej spiżarni: dodawaj produkty z jednostkami i cenami, edytuj i usuwaj je, używaj jako składników w przepisach. Podczas gotowania użyte produkty mogą być automatycznie odliczane z zapasów.',
          search: 'Wyszukiwanie przepisów znajduje dania według nazwy, tagów lub posiadanych składników. Łącz filtry, aby odpowiedzieć na pytanie „co ugotować z tego, co jest w lodówce”.'
        },
        smart_home: {
          main: 'Panel urządzeń pokazuje wszystkie urządzenia smart home jako karty ze statusem na żywo: online/offline, pokój, aktualna wartość i szybki przełącznik. Urządzenia są grupowane według pokoi; ikony w nagłówku przełączają tryb siatka/tabela i otwierają ustawienia globalne (broker MQTT, integracje).',
          device: 'Kliknięcie karty urządzenia otwiera panel sterowania: plakietka statusu, tagi pokoju i typu, sekcja „Sterowanie” z przełącznikami/suwakami (zasilanie, timer auto-wyłączenia, jasność), parametry na żywo (pobór mocy, temperatura) i przycisk „Edytuj”.'
        }
      },
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
      instructions_btn: 'Zobrazit návod', instructions_close: 'Zavřít návod', instructions_title: 'Jak používat',
      instructions_sections: {
        cloud: { main: 'Správce souborů', markdown: 'Editor Markdown', text: 'Textový editor', image: 'Editor fotek', zip: 'Prohlížeč archivů', pdf: 'Prohlížeč PDF' },
        steps: { main: 'Panel a kalendář', stats: 'Statistiky', info: 'Informace o aplikaci' },
        spends: { main: 'Přehled výdajů', table: 'Tabulka výdajů', settings: 'Obchody a nastavení', analytics: 'Statistiky a grafy' },
        lopaka: { main: 'Editor a generátor kódu' },
        portal: { home: 'Hlavní stránka', main: 'Panel aplikací', login: 'Přihlášení', register: 'Registrace' },
        films: { main: 'Kolekce filmů', add: 'Přidání filmu', settings: 'Nastavení a správa' },
        kanboard: { main: 'Přehled', board: 'Kanban tabule', task: 'Zobrazení úkolu' },
        recipes: { main: 'Kuchařka', products: 'Správa produktů', search: 'Hledání receptů' },
        smart_home: { main: 'Panel zařízení', device: 'Ovládání zařízení' }
      },
      instructions_texts: {
        cloud: {
          main: 'Správce souborů je srdcem cloudového úložiště. Levý panel zobrazuje strom složek — klikněte na složku, kterou chcete otevřít, použijte kontextovou nabídku (pravé tlačítko) nebo tlačítka panelu pro vytváření složek a nahrávání souborů přetažením. Dvojité kliknutí otevře soubor v příslušném vestavěném editoru. Soubory lze přesouvat, přejmenovávat, kopírovat, sdílet odkazem a mazat. Horní panel ukazuje kvótu a umožňuje vyhledávání ve všech souborech.',
          markdown: 'Editor Markdown otevírá soubory .md. Levá polovina je textový editor se zvýrazněním syntaxe, pravá živý náhled hotového dokumentu. Panel nástrojů vkládá nadpisy, seznamy, odkazy, tabulky, bloky kódu a obrázky. Změny se ukládají automaticky; přes Soubor → Export lze výsledek stáhnout jako HTML nebo PDF.',
          text: 'Textový editor otevírá obyčejné textové soubory a kód (js, py, css, json, ini a další). Jde o editor Monaco se zvýrazněním syntaxe, automatickým odsazováním, hledáním a nahrazováním (Ctrl+F / Ctrl+H) a více kartami. Vše se ukládá zpět do cloudového úložiště.',
          image: 'Editor fotek otevírá fotografie a obrázky. Lze oříznout, otočit a překlopit, upravit jas, kontrast a saturaci, použít filtry, přidat text a tvary, změnit velikost. Hotový výsledek se ukládá jako PNG nebo JPEG zpět do úložiště.',
          zip: 'Prohlížeč archivů otevírá soubory .zip bez stahování. Procházejte obsah archivu jako strom složek, prohlížejte si soubory uvnitř a rozbalte jednotlivé soubory nebo celý archiv do libovolné složky. Lze také vytvořit nový .zip z vybraných souborů a složek.',
          pdf: 'Prohlížeč PDF otevírá dokumenty .pdf s náhledy stránek, přibližováním, fulltextovým vyhledáváním a navigací přes záložky. K dispozici jsou také anotace a tisk.'
        },
        steps: {
          main: 'Panel ukazuje dnešní kroky, vzdálenost, kalorie a kruh postupu k dennímu cíli. Kalendář níže zobrazuje historii za celý měsíc — dny jsou obarveny podle úrovně aktivity. Tlačítko „+” přidává kroky ručně, např. po procházce bez telefonu.',
          stats: 'Statistiky agregují aktivitu: rozložení kroků podle let, mapu intenzity podle měsíců a trendy za týdny a měsíce. Grafy se přepínají mezi kroky, vzdáleností a kaloriemi.',
          info: 'Sekce „Informace” vysvětluje, jak spárovat aplikaci pro Android: naskenujte QR kód, abyste propojili telefon, poté se kroky synchronizují automaticky na pozadí. Zde lze také nastavit upozornění a export dat.'
        },
        spends: {
          main: 'Hlavní pohled je panel výdajů: suma za aktuální měsíc, porovnání s předchozím, rozpad podle obchodů a kategorií a graf dynamiky. Přidejte výdaj tlačítkem „+”: vyberte obchod, částku, datum a případně fotografii účtenky.',
          table: 'Tabulka výdajů je seznam všech nákupů ve formě tabulky. Filtrujte podle měsíce, obchodu nebo kategorie, třiďte podle libovolného sloupce, upravujte buňky na místě a mažte chybné záznamy.',
          settings: 'Nastavení spravují obchody (s logy a kategoriemi), měnu a kurzy, měsíční limity rozpočtu, OCR fotografií účtenek a import/export CSV pro zálohy nebo migraci.',
          analytics: 'Statistiky a grafy ukazují výdaje za měsíc, čtvrtletí a rok, koláčové grafy kategorií, trendy obchodů a průměrný účet.'
        },
        lopaka: {
          main: 'Lopaka je grafický editor a generátor kódu pro vestavěné displeje. Vyberte knihovnu platformy (TFT_eSPI, U8g2, LovyanGFX, Adafruit GFX, ESPHome, Flipper atd.) a rozlišení displeje, kreslete panelem nástrojů: Výběr (V), Obrázek (I), Štětec (B), Text (T), Obdélník (R), Elipsa (O), Linie (L), Trojúhelník a Polygon. Každý tvar se stane vrstvou — přesouvejte, měňte velikost a zarovnávejte v inspektoru vpravo. Panel kódu dole generuje hotový kód firmwaru (C/C++, MicroPython, ESPHome YAML); stiskněte COPY pro zkopírování kódu nebo SAVE pro uložení projektu. Zkratky: Ctrl+Z zpět, Del maže vybranou vrstvu, šipky — posun o 1 px.'
        },
        portal: {
          home: 'Hlavní stránka je veřejný vstupní bod platformy: popisuje dostupné aplikace, ukazuje nejnovější zprávy, umožňuje vybrat jazyk (6 jazyků) a barevný motiv. Odsud se lze přihlásit, zaregistrovat nebo otevřít přihlášení jednorázovým PIN-em.',
          main: 'Panel aplikací (Shell Application) je váš osobní hub po přihlášení. Všechny nainstalované aplikace jsou zobrazeny jako dlaždice — klikněte pro otevření. Horní panel nabízí oznámení, chat podpory a postranní menu; v menu rychlá navigace a akce profilu/sdílení.',
          login: 'Stránka přihlášení podporuje Google OAuth a klasické přihlášení e-mail/heslo. Po několika neúspěšných pokusech může být vyžadován jednorázový PIN (OTP) z e-mailu. Relace se sledují podle zařízení — lze je zobrazit a ukončit v profilu.',
          register: 'Registrace vytvoří bezplatný účet: zadejte e-mail, jméno a heslo, potvrďte kódem ze zprávy — a máte přístup ke všem vestavěným aplikacím s osobní izolací dat.'
        },
        films: {
          main: 'Kolekce filmů zobrazuje knihovnu jako karty s plakáty, hodnoceními a kategoriemi. Vyhledávání a filtry pomohou rychle najít titul; otevřete kartu pro detaily, komentáře a tlačítko přehrání. Postranní menu obsahuje navigaci: kolekce, přidat film, nastavení.',
          add: 'Dialog „Přidat nový film” vytvoří záznam: zadejte název, původní jazyk, rok, typ obsahu (online odkaz nebo lokální soubor), samotný odkaz, kategorii a hvězdičkové hodnocení. Film se okamžitě objeví v kolekci a přehraje se vestavěným přehrávačem.',
          settings: 'Nastavení (pro správce) spravují celou knihovnu: záložka „Správa filmů” ukazuje všechny tituly s přepínači viditelnosti a mazáním, „Správa komentářů” moderuje komentáře uživatelů a záložka „Nastavení” uchovává předvolby přehrávače a úložiště.'
        },
        kanboard: {
          main: 'Přehled je vstupní bod Kanboardu: ukazuje projekty s počítadly úkolů podle sloupců, seznamy „Moje úkoly” a „Moje podúkoly” a proud aktivity. „Nový projekt” vytvoří tabuli.',
          board: 'Kanban tabule znázorňuje projekt jako sloupce (Backlog, Todo, V řešení, Hotovo…). Přetahujte karty mezi sloupci pro změnu stavu, „+” ve sloupci přidá úkol, vyhledávací řádek filtruje podle stavu/řešitele/štítku.',
          task: 'Zobrazení úkolu shromažďuje vše o úkolu: přehled (stav, priorita, řešitel, data), popis, podúkoly s postupem, interní/externí odkazy, přílohy a vlákno komentářů. Levý panel nabízí akce: úprava, opakování, podúkoly, odkazy, komentáře, duplikování, přesun do jiného projektu.'
        },
        recipes: {
          main: 'Kuchařka zobrazuje recepty jako karty s fotkami a tagy. Vytvořte recept se surovinami (propojenými se seznamem produktů), podrobnými instrukcemi a fotkou. Tlačítka panelu otevírají správu produktů, hledání receptů a nastavení; recepty lze sdílet odkazem.',
          products: 'Správa produktů vede databázi vaší spíže: přidávejte produkty s jednotkami a cenami, upravujte a mazejte je, používejte jako suroviny v receptech. Při vaření lze použité produkty automaticky odepsat ze zásob.',
          search: 'Hledání receptů najde pokrmy podle názvu, tagů nebo dostupných surovin. Kombinujte filtry, abyste odpověděli na otázku „co uvařit z toho, co je v lednici”.'
        },
        smart_home: {
          main: 'Panel zařízení ukazuje všechna zařízení chytré domácnosti jako karty se stavem v reálném čase: online/offline, pokoj, aktuální hodnota a rychlý přepínač. Zařízení se seskupují podle pokojů; ikony v záhlaví přepínají režim mřížka/tabulka a otevírají globální nastavení (MQTT broker, integrace).',
          device: 'Kliknutí na kartu zařízení otevře panel ovládání: odznak stavu, tagy pokoje a typu, sekce „Ovládání” s přepínači/posuvníky (napájení, časovač auto-vypnutí, jas), živé parametry (odběr, teplota) a tlačítko „Upravit”.'
        }
      },
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
  const PREVIEWS = {
    portal: ['site_home_light.jpg', 'site_home_dark.jpg', 'site_main_light.jpg', 'site_main_dark.jpg', 'site_login_light.jpg', 'site_login_dark.jpg', 'site_register_light.jpg', 'site_register_dark.jpg'],
    cloud: ['cloud_main_light.jpg', 'cloud_main_dark.jpg', 'cloud_markdown_light.jpg', 'cloud_markdown_dark.jpg', 'cloud_text_light.jpg', 'cloud_text_dark.jpg', 'cloud_image_light.jpg', 'cloud_image_dark.jpg', 'cloud_zip_light.jpg', 'cloud_zip_dark.jpg', 'cloud_pdf_light.jpg', 'cloud_pdf_dark.jpg'],
    films: ['films_main_light.jpg', 'films_main_dark.jpg', 'films_add_light.jpg', 'films_add_dark.jpg', 'films_settings_light.jpg', 'films_settings_dark.jpg'],
    dawarich: [],
    gitea: [], immich: [],    lopaka: ['lopaka_main_light.jpg', 'lopaka_main_dark.jpg'],
    kanboard: ['kanboard_main_light.jpg', 'kanboard_main_dark.jpg', 'kanboard_board_light.jpg', 'kanboard_board_dark.jpg', 'kanboard_task_light.jpg', 'kanboard_task_dark.jpg'],
    recipes: ['recipes_main_light.jpg', 'recipes_main_dark.jpg', 'recipes_products_light.jpg', 'recipes_products_dark.jpg', 'recipes_search_light.jpg', 'recipes_search_dark.jpg'],
    smart_home: ['smart_home_main_light.jpg', 'smart_home_main_dark.jpg', 'smart_home_device_light.jpg', 'smart_home_device_dark.jpg'],
    spends: ['spends_main_light.jpg', 'spends_main_dark.jpg'],
    steps: ['steps_main_light.jpg', 'steps_main_dark.jpg', 'steps_stats_light.jpg', 'steps_stats_dark.jpg', 'steps_info_light.jpg', 'steps_info_dark.jpg'],
    spends: ['spends_main_light.jpg', 'spends_main_dark.jpg', 'spends_table_light.jpg', 'spends_table_dark.jpg', 'spends_settings_light.jpg', 'spends_settings_dark.jpg', 'spends_analytics_light.jpg', 'spends_analytics_dark.jpg']
  };

  function webSrc(f) { return PREV_ORIG + f.replace(/\.(jpe?g|png)$/i, '.webp'); }

  // Per-app instruction sections; images named *_<theme>.jpg are filtered by current theme.
  // Per-app instructions: sections render title, optional text and a themed screenshot.
  const INSTRUCTIONS = {
    cloud: [
      { key: 'main', img: 'cloud_main' },
      { key: 'markdown', img: 'cloud_markdown' },
      { key: 'text', img: 'cloud_text' },
      { key: 'image', img: 'cloud_image' },
      { key: 'zip', img: 'cloud_zip' },
      { key: 'pdf', img: 'cloud_pdf' }
    ],
    steps: [
      { key: 'main', img: 'steps_main' },
      { key: 'stats', img: 'steps_stats' },
      { key: 'info', img: 'steps_info' }
    ],
    spends: [
      { key: 'main', img: 'spends_main' },
      { key: 'table', img: 'spends_table' },
      { key: 'settings', img: 'spends_settings' },
      { key: 'analytics', img: 'spends_analytics' }
    ],
    lopaka: [
      { key: 'main', img: 'lopaka_main' }
    ],
    portal: [
      { key: 'home', img: 'site_home' },
      { key: 'main', img: 'site_main' },
      { key: 'login', img: 'site_login' },
      { key: 'register', img: 'site_register' }
    ],
    films: [
      { key: 'main', img: 'films_main' },
      { key: 'add', img: 'films_add' },
      { key: 'settings', img: 'films_settings' }
    ],
    kanboard: [
      { key: 'main', img: 'kanboard_main' },
      { key: 'board', img: 'kanboard_board' },
      { key: 'task', img: 'kanboard_task' }
    ],
    recipes: [
      { key: 'main', img: 'recipes_main' },
      { key: 'products', img: 'recipes_products' },
      { key: 'search', img: 'recipes_search' }
    ],
    smart_home: [
      { key: 'main', img: 'smart_home_main' },
      { key: 'device', img: 'smart_home_device' }
    ]
  };

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
  const instructionsView = $('instructionsView');
  const instructionsBody = $('instructionsBody');
  const instructionsTitle = $('instructionsTitle');
  let currentModalId = null, currentModalLang = 'en';
  let instructionsOpen = false;
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
    currentModalId = id; currentModalLang = l;
    renderModal();
    modal.classList.add('active');
  }

  function renderModal() {
    const id = currentModalId, l = currentModalLang;
    if (!id) return;
    const t = I18N[l] || I18N.en;
    const name = id === 'portal' ? t.portal_title : (APPS.find(a => a.id === id)?.name || id);
    modalTitle.textContent = name;
    modalDesc.textContent = t.app_details[id] || '';
    modalGallery.innerHTML = '';
    const all = (PREVIEWS[id] || []).slice();
    const files = all.filter(f => f.includes('_' + theme + '.'));
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
    // Instructions button
    let btn = $('modalInstructionsBtn');
    if (!btn) {
      btn = document.createElement('button');
      btn.id = 'modalInstructionsBtn';
      btn.className = 'instructions-btn';
      btn.addEventListener('click', e => { e.stopPropagation(); openInstructions(); });
      modalGallery.parentNode.insertBefore(btn, modalGallery.nextSibling);
    }
    btn.textContent = '📖 ' + (t.instructions_btn || 'View instructions');
    btn.style.display = (INSTRUCTIONS[id]) ? '' : 'none';
  }

  function openInstructions() {
    renderInstructions();
    instructionsView.classList.add('active');
    instructionsOpen = true;
    document.body.style.overflow = 'hidden';
  }

  function closeInstructions() {
    instructionsView.classList.remove('active');
    instructionsOpen = false;
    document.body.style.overflow = '';
  }

  function renderInstructions() {
    const t = I18N[currentModalLang] || I18N.en;
    const name = currentModalId === 'portal' ? t.portal_title : (APPS.find(a => a.id === currentModalId)?.name || currentModalId);
    instructionsTitle.textContent = (t.instructions_title || 'How to use') + ' — ' + name;
    instructionsBody.innerHTML = '';
    const sections = INSTRUCTIONS[currentModalId] || [];
    const texts = (t.instructions_texts || {})[currentModalId] || {};
    sections.forEach(sec => {
      const item = document.createElement('div');
      item.className = 'instructions-item';
      const h = document.createElement('h4');
      h.textContent = t.instructions_sections?.[currentModalId]?.[sec.key] || sec.key;
      item.appendChild(h);
      const txt = texts[sec.key];
      if (txt) {
        const p = document.createElement('p');
        p.className = 'instructions-text';
        p.textContent = txt;
        item.appendChild(p);
      }
      const f = sec.img + '_' + theme + '.jpg';
      const img = document.createElement('img');
      img.src = webSrc(f);
      img.onerror = function () { this.onerror = null; this.src = PREV_ORIG + f; };
      img.alt = name + ' — ' + (t.instructions_sections?.[currentModalId]?.[sec.key] || sec.key);
      img.className = 'instructions-img';
      item.appendChild(img);
      instructionsBody.appendChild(item);
    });
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
    if (modal.classList.contains('active')) renderModal();
    if (instructionsOpen) renderInstructions();
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

  $('instructionsClose').addEventListener('click', closeInstructions);
  window.addEventListener('click', e => { if (e.target === instructionsView) closeInstructions(); });

  $('lightboxClose').addEventListener('click', () => lightbox.classList.remove('active'));
  $('lightboxPrev').addEventListener('click', () => moveLightbox(-1));
  $('lightboxNext').addEventListener('click', () => moveLightbox(1));
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      closeLanguageMenu();
      if ($('navDrawer').classList.contains('open')) { closeNav(); return; }
      if (instructionsOpen) { closeInstructions(); return; }
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
