(function () {
  const LANG_OPTIONS = {
    en: { label: 'English', flag: 'lang-flag-en' },
    uk: { label: 'Українська', flag: 'lang-flag-uk' },
    ru: { label: 'Русский', flag: 'lang-flag-ru' },
    be: { label: 'Беларуская', flag: 'lang-flag-be' },
    pl: { label: 'Polski', flag: 'lang-flag-pl' },
    cs: { label: 'Čeština', flag: 'lang-flag-cs' }
  };
  const I18N = {
    en: {
      status: { 400: 'Bad request', 401: 'Authentication required', 403: 'Access denied', 404: 'Not found', 405: 'Method not allowed', 408: 'Request timeout', 413: 'Request too large', 429: 'Too many requests', 500: 'Server error', 501: 'Not implemented', 502: 'Bad gateway', 503: 'Service unavailable', 504: 'Gateway timeout' },
      title: 'Something went wrong',
      message: 'The service could not complete this request. Try again or contact the administrator.',
      path: 'Path',
      time: 'Time',
      retry: 'Try again',
      home: 'Home',
      contact: 'Write administrator',
      subject: 'ServerKherson error',
      body: 'Hello. I received an error on ServerKherson.'
    },
    uk: {
      status: { 400: 'Некоректний запит', 401: 'Потрібна авторизація', 403: 'Доступ заборонено', 404: 'Не знайдено', 405: 'Метод не дозволено', 408: 'Час запиту минув', 413: 'Запит завеликий', 429: 'Забагато запитів', 500: 'Помилка сервера', 501: 'Не реалізовано', 502: 'Помилка шлюзу', 503: 'Сервіс недоступний', 504: 'Час шлюзу минув' },
      title: 'Щось пішло не так',
      message: 'Сервіс не зміг виконати цей запит. Спробуйте ще раз або напишіть адміністратору.',
      path: 'Шлях',
      time: 'Час',
      retry: 'Спробувати ще раз',
      home: 'Головна',
      contact: 'Написати адміністратору',
      subject: 'Помилка ServerKherson',
      body: 'Вітаю. Я отримав помилку на ServerKherson.'
    },
    ru: {
      status: { 400: 'Некорректный запрос', 401: 'Нужна авторизация', 403: 'Доступ запрещён', 404: 'Не найдено', 405: 'Метод не разрешён', 408: 'Время запроса истекло', 413: 'Запрос слишком большой', 429: 'Слишком много запросов', 500: 'Ошибка сервера', 501: 'Не реализовано', 502: 'Ошибка шлюза', 503: 'Сервис недоступен', 504: 'Время шлюза истекло' },
      title: 'Что-то пошло не так',
      message: 'Сервис не смог выполнить этот запрос. Попробуйте ещё раз или напишите администратору.',
      path: 'Путь',
      time: 'Время',
      retry: 'Попробовать снова',
      home: 'Главная',
      contact: 'Написать администратору',
      subject: 'Ошибка ServerKherson',
      body: 'Здравствуйте. Я получил ошибку на ServerKherson.'
    },
    be: {
      status: { 400: 'Некарэктны запыт', 401: 'Патрэбна аўтарызацыя', 403: 'Доступ забаронены', 404: 'Не знойдзена', 405: 'Метад не дазволены', 408: 'Час запыту скончыўся', 413: 'Запыт занадта вялікі', 429: 'Занадта шмат запытаў', 500: 'Памылка сервера', 501: 'Не рэалізавана', 502: 'Памылка шлюза', 503: 'Сэрвіс недаступны', 504: 'Час шлюза скончыўся' },
      title: 'Нешта пайшло не так',
      message: 'Сэрвіс не змог выканаць гэты запыт. Паспрабуйце яшчэ раз або напішыце адміністратару.',
      path: 'Шлях',
      time: 'Час',
      retry: 'Паспрабаваць яшчэ раз',
      home: 'Галоўная',
      contact: 'Напісаць адміністратару',
      subject: 'Памылка ServerKherson',
      body: 'Вітаю. Я атрымаў памылку на ServerKherson.'
    },
    pl: {
      status: { 400: 'Nieprawidłowe żądanie', 401: 'Wymagana autoryzacja', 403: 'Brak dostępu', 404: 'Nie znaleziono', 405: 'Metoda niedozwolona', 408: 'Limit czasu żądania', 413: 'Żądanie jest zbyt duże', 429: 'Za dużo żądań', 500: 'Błąd serwera', 501: 'Nie zaimplementowano', 502: 'Błąd bramy', 503: 'Usługa niedostępna', 504: 'Limit czasu bramy' },
      title: 'Coś poszło nie tak',
      message: 'Usługa nie mogła obsłużyć tego żądania. Spróbuj ponownie albo napisz do administratora.',
      path: 'Ścieżka',
      time: 'Czas',
      retry: 'Spróbuj ponownie',
      home: 'Strona główna',
      contact: 'Napisz do administratora',
      subject: 'Błąd ServerKherson',
      body: 'Dzień dobry. Otrzymałem błąd w ServerKherson.'
    },
    cs: {
      status: { 400: 'Neplatný požadavek', 401: 'Je vyžadováno přihlášení', 403: 'Přístup odepřen', 404: 'Nenalezeno', 405: 'Metoda není povolena', 408: 'Vypršel čas požadavku', 413: 'Požadavek je příliš velký', 429: 'Příliš mnoho požadavků', 500: 'Chyba serveru', 501: 'Není implementováno', 502: 'Chyba brány', 503: 'Služba není dostupná', 504: 'Vypršel čas brány' },
      title: 'Něco se pokazilo',
      message: 'Služba nemohla tento požadavek dokončit. Zkuste to znovu nebo napište administrátorovi.',
      path: 'Cesta',
      time: 'Čas',
      retry: 'Zkusit znovu',
      home: 'Domů',
      contact: 'Napsat administrátorovi',
      subject: 'Chyba ServerKherson',
      body: 'Dobrý den. Na ServerKherson jsem obdržel chybu.'
    }
  };
  const $ = id => document.getElementById(id);
  const rawCode = String(document.body.dataset.errorCode || new URLSearchParams(location.search).get('code') || '500');
  const errorCode = rawCode.match(/^\d{3}$/) ? rawCode : '500';
  let lang = localStorage.getItem('sk_home_lang') || new URLSearchParams(location.search).get('lang') || (navigator.language || 'en').slice(0, 2);
  let theme = localStorage.getItem('sk_home_theme') || new URLSearchParams(location.search).get('theme') || 'dark';
  if (!I18N[lang]) lang = 'en';
  function closeLanguageMenu() {
    const selector = $('langSelector');
    const button = $('langSelectorButton');
    if (!selector || !button) return;
    selector.classList.remove('open');
    button.setAttribute('aria-expanded', 'false');
  }
  function updateLanguageMenu() {
    const selector = $('langSelector');
    const button = $('langSelectorButton');
    const item = LANG_OPTIONS[lang] || LANG_OPTIONS.en;
    selector.dataset.value = lang;
    button.querySelector('.lang-selector-label').textContent = item.label;
    button.querySelector('.lang-flag').className = 'lang-flag ' + item.flag;
    selector.querySelectorAll('.lang-selector-option').forEach(option => {
      const active = option.dataset.lang === lang;
      option.classList.toggle('active', active);
      option.setAttribute('aria-selected', active ? 'true' : 'false');
    });
  }
  function render() {
    const t = I18N[lang] || I18N.en;
    document.documentElement.lang = lang;
    document.title = `ServerKherson — ${errorCode}`;
    $('errorCode').textContent = errorCode;
    $('errorStatusText').textContent = t.status[errorCode] || t.status[500];
    $('errorTitle').textContent = t.title;
    $('errorMessage').textContent = t.message;
    $('pathLabel').textContent = t.path;
    $('timeLabel').textContent = t.time;
    $('errorPath').textContent = location.pathname + location.search;
    $('errorTime').textContent = new Date().toLocaleString(lang === 'en' ? 'en-GB' : lang);
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.dataset.i18n;
      if (t[key]) el.textContent = t[key];
    });
    updateLanguageMenu();
  }
  function setTheme(value) {
    theme = value === 'light' ? 'light' : 'dark';
    localStorage.setItem('sk_home_theme', theme);
    document.body.classList.toggle('light-theme', theme === 'light');
    document.querySelectorAll('#themeToggle button').forEach(button => {
      button.classList.toggle('active', button.dataset.theme === theme);
    });
  }
  function initLanguageMenu() {
    const selector = $('langSelector');
    const button = $('langSelectorButton');
    button.addEventListener('click', event => {
      event.stopPropagation();
      const open = selector.classList.toggle('open');
      button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    selector.querySelectorAll('.lang-selector-option').forEach(option => {
      option.addEventListener('click', event => {
        event.stopPropagation();
        lang = option.dataset.lang || 'en';
        localStorage.setItem('sk_home_lang', lang);
        closeLanguageMenu();
        render();
      });
    });
    document.addEventListener('click', event => {
      if (!selector.contains(event.target)) closeLanguageMenu();
    });
  }
  function contactAdmin() {
    const t = I18N[lang] || I18N.en;
    const body = `${t.body}\n\nCode: ${errorCode}\nURL: ${location.href}\nTime: ${new Date().toISOString()}`;
    window.open(`https://mail.google.com/mail/?view=cm&fs=1&to=home260khersonserver@gmail.com&su=${encodeURIComponent(t.subject)}&body=${encodeURIComponent(body)}`, '_blank');
  }
  initLanguageMenu();
  document.querySelectorAll('#themeToggle button').forEach(button => button.addEventListener('click', () => setTheme(button.dataset.theme)));
  $('retryBtn').addEventListener('click', () => location.reload());
  $('adminBtn').addEventListener('click', contactAdmin);
  setTheme(theme);
  render();
})();
