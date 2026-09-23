/*
 * Lopaka i18n overlay.
 *
 * The Lopaka app is shipped as a pre-built bundle with an English-only UI.
 * This script translates the visible UI at runtime without touching the bundle.
 *
 * The active language is taken from the page link (?lang=...), which the
 * appdashboard already appends when opening the app. Supported languages:
 * en, ru, uk, be, pl, cs. Anything else (or a missing parameter) falls back
 * to English.
 *
 * Implementation notes:
 *  - Translation happens at the DOM level: text nodes, tooltips (data-tip),
 *    placeholders, titles and aria-labels whose (trimmed) value matches a
 *    known English string are replaced with the translation.
 *  - A MutationObserver re-applies translations when Vue re-renders parts of
 *    the UI, so dynamically recreated nodes stay translated.
 *  - The Ace code editor (.ace_editor), canvas and editable fields are never
 *    touched, so generated source code and user data stay intact.
 */
(function () {
    'use strict';

    var SUPPORTED = ['en', 'ru', 'uk', 'be', 'pl', 'cs'];
    var ALIASES = { ua: 'uk', by: 'be', cz: 'cs' };

    function normalizeLang(code) {
        code = String(code || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        if (ALIASES[code]) code = ALIASES[code];
        return SUPPORTED.indexOf(code) !== -1 ? code : '';
    }

    function cookieLang() {
        var m = document.cookie.match(/(?:^|;\s*)sk_language=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    var params = new URLSearchParams(window.location.search);
    var lang = normalizeLang(params.get('lang')) || normalizeLang(cookieLang()) || 'en';

    if (lang === 'en') {
        document.documentElement.lang = 'en';
        window.__lopakaI18n = { lang: 'en', apply: function () {} };
        return;
    }

    /* English string -> translations for the other 5 supported languages. */
    var T = {
        /* Toolbar */
        'Select': { ru: 'Выбрать', uk: 'Вибрати', be: 'Выбраць', pl: 'Wybierz', cs: 'Vybrat' },
        'image': { ru: 'изображение', uk: 'зображення', be: 'выява', pl: 'obraz', cs: 'obrázek' },
        'paint': { ru: 'кисть', uk: 'пензель', be: 'пэндзаль', pl: 'pędzel', cs: 'štětec' },
        'string': { ru: 'текст', uk: 'текст', be: 'тэкст', pl: 'tekst', cs: 'text' },
        'Rectangle': { ru: 'Прямоугольник', uk: 'Прямокутник', be: 'Прамавугольнік', pl: 'Prostokąt', cs: 'Obdélník' },
        'ellipse': { ru: 'эллипс', uk: 'еліпс', be: 'эліпс', pl: 'elipsa', cs: 'elipsa' },
        'line': { ru: 'линия', uk: 'лінія', be: 'лінія', pl: 'linia', cs: 'čára' },
        'Triangle': { ru: 'Треугольник', uk: 'Трикутник', be: 'Трохвугольнік', pl: 'Trójkąt', cs: 'Trojúhelník' },
        'polygon': { ru: 'многоугольник', uk: 'багатокутник', be: 'шматкутнік', pl: 'wielokąt', cs: 'mnohoúhelník' },

        /* Top bar */
        'Library:': { ru: 'Библиотека:', uk: 'Бібліотека:', be: 'Бібліятэка:', pl: 'Biblioteka:', cs: 'Knihovna:' },
        'Display:': { ru: 'Дисплей:', uk: 'Дисплей:', be: 'Дысплей:', pl: 'Ekran:', cs: 'Displej:' },
        'Background:': { ru: 'Фон:', uk: 'Фон:', be: 'Фон:', pl: 'Tło:', cs: 'Pozadí:' },
        'Custom...': { ru: 'Свой...', uk: 'Свій...', be: 'Свой...', pl: 'Własny...', cs: 'Vlastní...' },
        'Custom': { ru: 'Свой', uk: 'Свій', be: 'Свой', pl: 'Własny', cs: 'Vlastní' },

        /* Shortcuts panel */
        'Shortcuts': { ru: 'Горячие клавиши', uk: 'Гарячі клавіші', be: 'Гарачыя клавішы', pl: 'Skróty', cs: 'Zkratky' },
        '— move 10px': { ru: '— переместить на 10px', uk: '— перемістити на 10px', be: '— перамясціць на 10px', pl: '— przesuń o 10px', cs: '— posunout o 10px' },
        '— reorder layers': { ru: '— изменить порядок слоёв', uk: '— змінити порядок шарів', be: '— змяніць парадак слаёў', pl: '— zmień kolejność warstw', cs: '— změnit pořadí vrstev' },
        '— copy/paste/cut': { ru: '— копировать/вставить/вырезать', uk: '— копіювати/вставити/вирізати', be: '— капіяваць/уставіць/выразаць', pl: '— kopiuj/wklej/wytnij', cs: '— kopírovat/vložit/vyjmout' },
        '— select all': { ru: '— выделить всё', uk: '— виділити все', be: '— вылучыць усё', pl: '— zaznacz wszystko', cs: '— vybrat vše' },
        '— undo': { ru: '— отменить', uk: '— скасувати', be: '— адмяніць', pl: '— cofnij', cs: '— zpět' },
        '— redo': { ru: '— повторить', uk: '— повторити', be: '— паўтарыць', pl: '— ponów', cs: '— znovu' },
        '— zoom in/out': { ru: '— масштабирование', uk: '— масштабування', be: '— маштабаванне', pl: '— powiększ/pomniejsz', cs: '— přiblížit/oddálit' },
        '— multi-select': { ru: '— множественный выбор', uk: '— множинний вибір', be: '— множны выбар', pl: '— zaznacz wiele', cs: '— vícenásobný výběr' },
        '— reset selection': { ru: '— сбросить выделение', uk: '— скинути виділення', be: '— скінуць вылучэнне', pl: '— wyczyść zaznaczenie', cs: '— zrušit výběr' },
        '— group layers': { ru: '— сгруппировать слои', uk: '— згрупувати шари', be: '— згрупаваць слаі', pl: '— zgrupuj warstwy', cs: '— seskupit vrstvy' },
        '— ungroup': { ru: '— разгруппировать', uk: '— розгрупувати', be: '— разгрупаваць', pl: '— rozgrupuj', cs: '— rozdělit skupinu' },
        'Resizing': { ru: 'Изменение размера', uk: 'Зміна розміру', be: 'Змяненне памеру', pl: 'Zmiana rozmiaru', cs: 'Změna velikosti' },
        'Hold': { ru: 'Удерживайте', uk: 'Утримуйте', be: 'Утрымлівайце', pl: 'Przytrzymaj', cs: 'Podržte' },
        'to keep aspect ratio': { ru: 'для сохранения пропорций', uk: 'щоб зберегти пропорції', be: 'каб захаваць прапорцыі', pl: 'aby zachować proporcje', cs: 'pro zachování poměru stran' },
        'to resize from center': { ru: 'для изменения размера от центра', uk: 'щоб змінювати розмір від центру', be: 'каб змяняць памер ад цэнтра', pl: 'aby zmieniać rozmiar od środka', cs: 'pro změnu velikosti od středu' },
        'Paint': { ru: 'Кисть', uk: 'Пензель', be: 'Пэндзаль', pl: 'Pędzel', cs: 'Štětec' },
        'to draw a line': { ru: 'для рисования линии', uk: 'щоб намалювати лінію', be: 'каб намаляваць лінію', pl: 'aby narysować linię', cs: 'pro kreslení čáry' },
        'RIGHT CLICK': { ru: 'ПРАВАЯ КНОПКА', uk: 'ПРАВА КНОПКА', be: 'ПРАВАЯ КНОПКА', pl: 'PRAWY PRZYCISK', cs: 'PRAVÉ TLAČÍTKO' },
        '— eraser': { ru: '— ластик', uk: '— гумка', be: '— гумка', pl: '— gumka', cs: '— guma' },
        'Polygon, Triangle': { ru: 'Многоугольник, треугольник', uk: 'Багатокутник, трикутник', be: 'Шматкутнік, трохвугольнік', pl: 'Wielokąt, trójkąt', cs: 'Mnohoúhelník, trojúhelník' },
        'CLICK': { ru: 'КЛИК', uk: 'КЛІК', be: 'КЛІК', pl: 'KLIKNIJ', cs: 'KLIKNĚTE' },
        '— place vertices': { ru: '— расставить вершины', uk: '— розставити вершини', be: '— расставіць вяршыні', pl: '— umieść wierzchołki', cs: '— umístit vrcholy' },
        'DOUBLE CLICK': { ru: 'ДВОЙНОЙ КЛИК', uk: 'ПОДВІЙНИЙ КЛІК', be: 'ПАДВОЙНЫ КЛІК', pl: 'PODWÓJNE KLIKNIĘCIE', cs: 'DVOJITÉ KLIKNUTÍ' },
        'or': { ru: 'или', uk: 'або', be: 'ці', pl: 'lub', cs: 'nebo' },
        '— finish': { ru: '— завершить', uk: '— завершити', be: '— завяршыць', pl: '— zakończ', cs: '— dokončit' },
        '— edit vertices': { ru: '— редактировать вершины', uk: '— редагувати вершини', be: '— рэдагаваць вяршыні', pl: '— edytuj wierzchołki', cs: '— upravit vrcholy' },

        /* Code panel */
        'Copy': { ru: 'Копировать', uk: 'Копіювати', be: 'Капіяваць', pl: 'Kopiuj', cs: 'Kopírovat' },
        'Wrapper function': { ru: 'Функция-обёртка', uk: 'Функція-обгортка', be: 'Функцыя-абгортка', pl: 'Funkcja opakowująca', cs: 'Obalovací funkce' },
        'Include fonts': { ru: 'Включить шрифты', uk: 'Включити шрифти', be: 'Уключыць шрыфты', pl: 'Dołącz czcionki', cs: 'Zahrnout písma' },
        'Declare images': { ru: 'Объявить изображения', uk: 'Оголосити зображення', be: "Аб'явіць выявы", pl: 'Zadeklaruj obrazy', cs: 'Deklarovat obrázky' },
        'Declare variables': { ru: 'Объявить переменные', uk: 'Оголосити змінні', be: "Аб'явіць зменныя", pl: 'Zadeklaruj zmienne', cs: 'Deklarovat proměnné' },
        'Layer titles': { ru: 'Названия слоёв', uk: 'Назви шарів', be: 'Назвы слаёў', pl: 'Nazwy warstw', cs: 'Názvy vrstev' },
        'Clear/Fill display': { ru: 'Очистка/заливка дисплея', uk: 'Очищення/заливка дисплея', be: 'Ачыстка/заліўка дысплея', pl: 'Wyczyść/wypełnij ekran', cs: 'Vymazat/vyplnit displej' },
        'Declare as PROGMEM': { ru: 'Объявить как PROGMEM', uk: 'Оголосити як PROGMEM', be: "Аб'явіць як PROGMEM", pl: 'Zadeklaruj jako PROGMEM', cs: 'Deklarovat jako PROGMEM' },
        'Syntax': { ru: 'Синтаксис', uk: 'Синтаксис', be: 'Сінтаксіс', pl: 'Składnia', cs: 'Syntaxe' },
        'Copy source code': { ru: 'Копировать исходный код', uk: 'Копіювати вихідний код', be: 'Капіяваць зыходны код', pl: 'Kopiuj kod źródłowy', cs: 'Kopírovat zdrojový kód' },
        'Import code - Experimental: Works best with code generated in Lopaka': {
            ru: 'Импорт кода — экспериментально: лучше всего работает с кодом, созданным в Lopaka',
            uk: 'Імпорт коду — експериментально: найкраще працює з кодом, створеним у Lopaka',
            be: 'Імпарт кода — эксперыментальна: лепш за ўсё працуе з кодам, створаным у Lopaka',
            pl: 'Import kodu — eksperymentalne: najlepiej działa z kodem wygenerowanym w Lopaka',
            cs: 'Import kódu — experimentální: nejlépe funguje s kódem vygenerovaným v Lopaka'
        },

        /* Layer inspector */
        'Font': { ru: 'Шрифт', uk: 'Шрифт', be: 'Шрыфт', pl: 'Czcionka', cs: 'Písmo' },
        'Size': { ru: 'Размер', uk: 'Розмір', be: 'Памер', pl: 'Rozmiar', cs: 'Velikost' },
        'Fill': { ru: 'Заливка', uk: 'Заливка', be: 'Заліўка', pl: 'Wypełnienie', cs: 'Výplň' },
        'Add variable': { ru: 'Добавить переменную', uk: 'Додати змінну', be: 'Дадаць зменную', pl: 'Dodaj zmienną', cs: 'Přidat proměnnou' },
        'Merge to Bitmap': { ru: 'Объединить в Bitmap', uk: "Об'єднати в Bitmap", be: "Аб'яднаць у Bitmap", pl: 'Połącz w bitmapę', cs: 'Sloučit do bitmapy' },
        'Merge layers into a single bitmap': { ru: 'Объединить слои в один bitmap', uk: 'Об’єднати шари в один bitmap', be: 'Аб’яднаць слаі ў адзін bitmap', pl: 'Połącz warstwy w jedną bitmapę', cs: 'Sloučit vrstvy do jedné bitmapy' },
        'Delete': { ru: 'Удалить', uk: 'Видалити', be: 'Выдаліць', pl: 'Usuń', cs: 'Smazat' },
        'Enter text...': { ru: 'Введите текст...', uk: 'Введіть текст...', be: 'Увядзіце тэкст...', pl: 'Wpisz tekst...', cs: 'Zadejte text...' },
        'Inverted (XOR)': { ru: 'Инвертированный (XOR)', uk: 'Інвертований (XOR)', be: 'Інвертаваны (XOR)', pl: 'Odwrócony (XOR)', cs: 'Invertovaný (XOR)' },
        'Filled': { ru: 'С заливкой', uk: 'Із заливкою', be: 'З заліўкай', pl: 'Wypełniony', cs: 'Vyplněný' },
        'Overlay': { ru: 'Наложение', uk: 'Накладання', be: 'Накладанне', pl: 'Nakładka', cs: 'Překrytí' },
        'Alpha channel': { ru: 'Альфа-канал', uk: 'Альфа-канал', be: 'Альфа-канал', pl: 'Kanał alfa', cs: 'Alfa kanál' },
        'Back': { ru: 'Фон', uk: 'Фон', be: 'Фон', pl: 'Tło', cs: 'Pozadí' },
        'Border': { ru: 'Граница', uk: 'Межа', be: 'Мяжа', pl: 'Obramowanie', cs: 'Okraj' },
        'Checked': { ru: 'Отмечено', uk: 'Позначено', be: 'Адзначана', pl: 'Zaznaczony', cs: 'Zaškrtnuto' },
        'Use alpha channel for RGB images': {
            ru: 'Использовать альфа-канал для RGB-изображений',
            uk: 'Використовувати альфа-канал для RGB-зображень',
            be: 'Выкарыстоўваць альфа-канал для RGB-выяў',
            pl: 'Użyj kanału alfa dla obrazów RGB',
            cs: 'Použít alfa kanál pro RGB obrázky'
        },
        'Dim the layer and skip it from code generation': {
            ru: 'Затемнить слой и исключить его из генерации кода',
            uk: 'Затемнити шар і виключити його з генерації коду',
            be: 'Зацямніць слой і выключыць яго з генерацыі кода',
            pl: 'Przyciemnij warstwę i pomiń ją w generowaniu kodu',
            cs: 'Ztmavit vrstvu a vynechat ji z generování kódu'
        },
        'Align left': { ru: 'Выровнять по левому краю', uk: 'Вирівняти по лівому краю', be: 'Выраўнаваць па левым краі', pl: 'Wyrównaj do lewej', cs: 'Zarovnat vlevo' },
        'Align horisontal centers': { ru: 'Выровнять по горизонтали по центру', uk: 'Вирівняти по горизонталі по центру', be: 'Выраўнаваць па гарызанталі па цэнтры', pl: 'Wyśrodkuj poziomo', cs: 'Zarovnat vodorovně na střed' },
        'Align right': { ru: 'Выровнять по правому краю', uk: 'Вирівняти по правому краю', be: 'Выраўнаваць па правым краі', pl: 'Wyrównaj do prawej', cs: 'Zarovnat vpravo' },
        'Align top': { ru: 'Выровнять по верхнему краю', uk: 'Вирівняти по верхньому краю', be: 'Выраўнаваць па верхнім краі', pl: 'Wyrównaj do góry', cs: 'Zarovnat nahoru' },
        'Align vertical centers': { ru: 'Выровнять по вертикали по центру', uk: 'Вирівняти по вертикалі по центру', be: 'Выраўнаваць па вертыкалі па цэнтры', pl: 'Wyśrodkuj pionowo', cs: 'Zarovnat svisle na střed' },
        'Align bottom': { ru: 'Выровнять по нижнему краю', uk: 'Вирівняти по нижньому краю', be: 'Выраўнаваць па ніжнім краі', pl: 'Wyrównaj do dołu', cs: 'Zarovnat dolů' },
        'Flip horizontally': { ru: 'Отразить по горизонтали', uk: 'Відобразити по горизонталі', be: 'Адлюстраваць па гарызанталі', pl: 'Odbij poziomo', cs: 'Převrátit vodorovně' },
        'Flip vertically': { ru: 'Отразить по вертикали', uk: 'Відобразити по вертикалі', be: 'Адлюстраваць па вертыкалі', pl: 'Odbij pionowo', cs: 'Převrátit svisle' },
        'Rotate clockwise': { ru: 'Повернуть по часовой стрелке', uk: 'Повернути за годинниковою стрілкою', be: 'Павярнуць па гадзіннікавай стрэлцы', pl: 'Obróć zgodnie z ruchem wskazówek', cs: 'Otočit po směru hodin' },
        'Invert colors': { ru: 'Инвертировать цвета', uk: 'Інвертувати кольори', be: 'Інвертаваць колеры', pl: 'Odwróć kolory', cs: 'Invertovat barvy' },
        'Remove blank padding': { ru: 'Удалить пустые поля', uk: 'Видалити порожні поля', be: 'Выдаліць пустыя палі', pl: 'Usuń puste marginesy', cs: 'Odstranit prázdné okraje' },
        'Download image': { ru: 'Скачать изображение', uk: 'Завантажити зображення', be: 'Спампаваць выяву', pl: 'Pobierz obraz', cs: 'Stáhnout obrázek' },
        'Add to Project assets': { ru: 'Добавить в ресурсы проекта', uk: 'Додати до ресурсів проєкту', be: 'Дадаць у рэсурсы праекта', pl: 'Dodaj do zasobów projektu', cs: 'Přidat do prostředků projektu' },
        'Selected layers': { ru: 'Выбрано слоёв', uk: 'Вибрано шарів', be: 'Выбрана слаёў', pl: 'Zaznaczono warstw', cs: 'Vybrané vrstvy' },

        /* Import image modal */
        'Icon Packs': { ru: 'Наборы иконок', uk: 'Набори іконок', be: 'Наборы іконак', pl: 'Pakiety ikon', cs: 'Sady ikon' },
        'Upload file': { ru: 'Загрузить файл', uk: 'Завантажити файл', be: 'Загрузіць файл', pl: 'Prześlij plik', cs: 'Nahrát soubor' },
        'Input byte array': { ru: 'Ввод байтового массива', uk: 'Введення байтового масиву', be: 'Увод байтавага масіва', pl: 'Wprowadź tablicę bajtów', cs: 'Zadat bajtové pole' },
        'Current Project': { ru: 'Текущий проект', uk: 'Поточний проєкт', be: 'Бягучы праект', pl: 'Bieżący projekt', cs: 'Aktuální projekt' },
        'Choose files or drag & drop them here': {
            ru: 'Выберите файлы или перетащите их сюда',
            uk: 'Виберіть файли або перетягніть їх сюди',
            be: 'Выберыце файлы або перацягніце іх сюды',
            pl: 'Wybierz pliki lub przeciągnij je tutaj',
            cs: 'Vyberte soubory nebo je sem přetáhněte'
        },
        'Browse files': { ru: 'Обзор файлов', uk: 'Огляд файлів', be: 'Агляд файлаў', pl: 'Przeglądaj pliki', cs: 'Procházet soubory' },
        'Name:': { ru: 'Имя:', uk: "Ім'я:", be: 'Імя:', pl: 'Nazwa:', cs: 'Název:' },
        'Zoom:': { ru: 'Масштаб:', uk: 'Масштаб:', be: 'Маштаб:', pl: 'Powiększenie:', cs: 'Přiblížení:' },
        'Monochrome': { ru: 'Монохромное', uk: 'Монохромне', be: 'Манахромнае', pl: 'Monochromatyczny', cs: 'Monochromatické' },
        'Width:': { ru: 'Ширина:', uk: 'Ширина:', be: 'Шырыня:', pl: 'Szerokość:', cs: 'Šířka:' },
        'Height:': { ru: 'Высота:', uk: 'Висота:', be: 'Вышыня:', pl: 'Wysokość:', cs: 'Výška:' },
        'Reset size': { ru: 'Сбросить размер', uk: 'Скинути розмір', be: 'Скінуць памер', pl: 'Resetuj rozmiar', cs: 'Resetovat velikost' },
        'Fit to screen': { ru: 'По размеру экрана', uk: 'За розміром екрана', be: 'Па памеры экрана', pl: 'Dopasuj do ekranu', cs: 'Přizpůsobit obrazovce' },
        'Crop W:': { ru: 'Обрезка Ш:', uk: 'Обрізання Ш:', be: 'Абрэзка Ш:', pl: 'Przycinanie S:', cs: 'Oříznutí Š:' },
        'Crop H:': { ru: 'Обрезка В:', uk: 'Обрізання В:', be: 'Абрэзка В:', pl: 'Przycinanie W:', cs: 'Oříznutí V:' },
        'Crop X:': { ru: 'Обрезка X:', uk: 'Обрізання X:', be: 'Абрэзка X:', pl: 'Przycinanie X:', cs: 'Oříznutí X:' },
        'Crop Y:': { ru: 'Обрезка Y:', uk: 'Обрізання Y:', be: 'Абрэзка Y:', pl: 'Przycinanie Y:', cs: 'Oříznutí Y:' },
        'Resampling': { ru: 'Ресемплирование', uk: 'Ресемплінг', be: 'Рэсемплінг', pl: 'Próbkowanie', cs: 'Převzorkování' },
        'none': { ru: 'нет', uk: 'ні', be: 'не', pl: 'brak', cs: 'žádné' },
        'nearest': { ru: 'ближайший', uk: 'найближчий', be: 'бліжэйшы', pl: 'najbliższy', cs: 'nejbližší' },
        'bilinear': { ru: 'билинейный', uk: 'білінійний', be: 'білінейны', pl: 'dwuliniowy', cs: 'bilineární' },
        'spline': { ru: 'сплайн', uk: 'сплайн', be: 'сплайн', pl: 'spline', cs: 'spline' },
        'lanczos': { ru: 'lanczos', uk: 'lanczos', be: 'lanczos', pl: 'lanczos', cs: 'lanczos' },
        'gaussian': { ru: 'гауссов', uk: 'гаусів', be: 'гаўсаў', pl: 'gaussowskie', cs: 'gaussovské' },
        'mitchell': { ru: 'mitchell', uk: 'mitchell', be: 'mitchell', pl: 'mitchell', cs: 'mitchell' },
        'Brightness': { ru: 'Яркость', uk: 'Яскравість', be: 'Яркасць', pl: 'Jasność', cs: 'Jas' },
        'Contrast': { ru: 'Контраст', uk: 'Контраст', be: 'Кантраст', pl: 'Kontrast', cs: 'Kontrast' },
        'Grayscale first': { ru: 'Сначала оттенки серого', uk: 'Спочатку відтінки сірого', be: 'Спачатку шэрыя адценні', pl: 'Najpierw skala szarości', cs: 'Nejprve stupně šedi' },
        'Dithering': { ru: 'Дизеринг', uk: 'Дизеринг', be: 'Дызерынг', pl: 'Dithering', cs: 'Dithering' },
        'Invert palette': { ru: 'Инвертировать палитру', uk: 'Інвертувати палітру', be: 'Інвертаваць палітру', pl: 'Odwróć paletę', cs: 'Invertovat paletu' },
        'Invert result': { ru: 'Инвертировать результат', uk: 'Інвертувати результат', be: 'Інвертаваць вынік', pl: 'Odwróć wynik', cs: 'Invertovat výsledek' },
        'Swap bytes': { ru: 'Поменять байты', uk: 'Поміняти байти', be: 'Памяняць байты', pl: 'Zamień bajty', cs: 'Prohodit bajty' },
        'Cancel': { ru: 'Отмена', uk: 'Скасувати', be: 'Адмяніць', pl: 'Anuluj', cs: 'Zrušit' },
        'Import': { ru: 'Импорт', uk: 'Імпорт', be: 'Імпарт', pl: 'Importuj', cs: 'Importovat' },
        'Processing image(s)...': { ru: 'Обработка изображений...', uk: 'Обробка зображень...', be: 'Апрацоўка выяў...', pl: 'Przetwarzanie obrazów...', cs: 'Zpracování obrázků...' },

        /* Set display size modal */
        'Set display size': { ru: 'Установить размер дисплея', uk: 'Встановити розмір дисплея', be: 'Усталяваць памер дысплея', pl: 'Ustaw rozmiar ekranu', cs: 'Nastavit velikost displeje' },
        'Save': { ru: 'Сохранить', uk: 'Зберегти', be: 'Захаваць', pl: 'Zapisz', cs: 'Uložit' },
        'Reset display': { ru: 'Сбросить дисплей', uk: 'Скинути дисплей', be: 'Скінуць дысплей', pl: 'Resetuj ekran', cs: 'Resetovat displej' },

        /* Font preview modal */
        'Font Preview': { ru: 'Предпросмотр шрифта', uk: 'Попередній перегляд шрифту', be: 'Папярэдні прагляд шрыфта', pl: 'Podgląd czcionki', cs: 'Náhled písma' },
        'Preview Zoom': { ru: 'Масштаб предпросмотра', uk: 'Масштаб попереднього перегляду', be: 'Маштаб папярэдняга прагляду', pl: 'Powiększenie podglądu', cs: 'Přiblížení náhledu' },
        'Font Height': { ru: 'Высота шрифта', uk: 'Висота шрифту', be: 'Вышыня шрыфта', pl: 'Wysokość czcionki', cs: 'Výška písma' },
        'Preview Text': { ru: 'Текст предпросмотра', uk: 'Текст попереднього перегляду', be: 'Тэкст папярэдняга прагляду', pl: 'Tekst podglądu', cs: 'Text náhledu' },
        'Fonts': { ru: 'Шрифты', uk: 'Шрифти', be: 'Шрыфты', pl: 'Czcionki', cs: 'Písma' },

        /* Flipper live view */
        'Live View': { ru: 'Живой просмотр', uk: 'Живий перегляд', be: 'Жывы прагляд', pl: 'Podgląd na żywo', cs: 'Živý náhled' },
        'Disconnect': { ru: 'Отключить', uk: 'Відключити', be: 'Адключыць', pl: 'Rozłącz', cs: 'Odpojit' },
        'Preview your design in real-time on your Flipper via USB': {
            ru: 'Просматривайте дизайн в реальном времени на Flipper через USB',
            uk: 'Переглядайте дизайн у реальному часі на Flipper через USB',
            be: 'Глядзіце дызайн у рэальным часе на Flipper праз USB',
            pl: 'Podglądaj projekt w czasie rzeczywistym na Flipper przez USB',
            cs: 'Zobrazte návrh v reálném čase na Flipper přes USB'
        },

        /* Misc */
        'Free Drawing mode': { ru: 'Режим свободного рисования', uk: 'Режим вільного малювання', be: 'Рэжым вольнага малявання', pl: 'Tryb swobodnego rysowania', cs: 'Režim volného kreslení' },
        'Paint color': { ru: 'Цвет кисти', uk: 'Колір пензля', be: 'Колер пэндзля', pl: 'Kolor pędzla', cs: 'Barva štětce' },
        'Copied to clipboard': { ru: 'Скопировано в буфер обмена', uk: 'Скопійовано в буфер обміну', be: 'Скапіявана ў буфер абмену', pl: 'Skopiowano do schowka', cs: 'Zkopírováno do schránky' },
        'Empty text': { ru: 'Пустой текст', uk: 'Порожній текст', be: 'Пусты тэкст', pl: 'Pusty tekst', cs: 'Prázdný text' },
        'Text Area': { ru: 'Текстовая область', uk: 'Текстова область', be: 'Тэкставая вобласць', pl: 'Obszar tekstowy', cs: 'Textová oblast' },
        'Graphics editor and image converter for Arduino, ESP32, Adafruit_GFX, u8g2, TFT_eSPI': {
            ru: 'Графический редактор и конвертер изображений для Arduino, ESP32, Adafruit_GFX, u8g2, TFT_eSPI',
            uk: 'Графічний редактор і конвертер зображень для Arduino, ESP32, Adafruit_GFX, u8g2, TFT_eSPI',
            be: 'Графічны рэдактар і канвертар выяў для Arduino, ESP32, Adafruit_GFX, u8g2, TFT_eSPI',
            pl: 'Edytor graficzny i konwerter obrazów dla Arduino, ESP32, Adafruit_GFX, u8g2, TFT_eSPI',
            cs: 'Grafický editor a převodník obrázků pro Arduino, ESP32, Adafruit_GFX, u8g2, TFT_eSPI'
        },

        /* Tools (title fallback = name) */
        'circle': { ru: 'круг', uk: 'коло', be: 'круг', pl: 'okrąg', cs: 'kruh' },
        'dot': { ru: 'точка', uk: 'крапка', be: 'кропка', pl: 'kropka', cs: 'bod' },
        'icon': { ru: 'иконка', uk: 'іконка', be: 'іконка', pl: 'ikona', cs: 'ikona' },
        'rect': { ru: 'прямоугольник', uk: 'прямокутник', be: 'прамавугольнік', pl: 'prostokąt', cs: 'obdélník' },
        'Button': { ru: 'Кнопка', uk: 'Кнопка', be: 'Кнопка', pl: 'Przycisk', cs: 'Tlačítko' },
        'Checkbox': { ru: 'Чекбокс', uk: 'Чекбокс', be: 'Чэкбокс', pl: 'Pole wyboru', cs: 'Zaškrtávací pole' },
        'Panel': { ru: 'Панель', uk: 'Панель', be: 'Панэль', pl: 'Panel', cs: 'Panel' },
        'Slider': { ru: 'Слайдер', uk: 'Слайдер', be: 'Слайдар', pl: 'Suwak', cs: 'Posuvník' },
        'Switch': { ru: 'Переключатель', uk: 'Перемикач', be: 'Пераключальнік', pl: 'Przełącznik', cs: 'Přepínač' },
        'Text Area': { ru: 'Текстовая область', uk: 'Текстова область', be: 'Тэкставая вобласць', pl: 'Obszar tekstowy', cs: 'Textová oblast' },
        'Fake Font': { ru: 'Псевдошрифт', uk: 'Псевдошрифт', be: 'Псеўдашрыфт', pl: 'Fałszywa czcionka', cs: 'Falešné písmo' },

        /* Layers panel */
        'Layers': { ru: 'Слои', uk: 'Шари', be: 'Слаі', pl: 'Warstwy', cs: 'Vrstvy' },

        /* Code panel topbar */
        'Code': { ru: 'Код', uk: 'Код', be: 'Код', pl: 'Kod', cs: 'Kód' },

        /* Save dialog */
        'Save project': { ru: 'Сохранить проект', uk: 'Зберегти проєкт', be: 'Захаваць праект', pl: 'Zapisz projekt', cs: 'Uložit projekt' },
        'Choose where to store your project (JSON file)': {
            ru: 'Выберите, где хранить проект (JSON-файл)',
            uk: 'Виберіть, де зберігати проєкт (JSON-файл)',
            be: 'Выберыце, дзе захоўваць праект (JSON-файл)',
            pl: 'Wybierz, gdzie przechowywać projekt (plik JSON)',
            cs: 'Zvolte, kde uložit projekt (soubor JSON)'
        },
        'Download to device': { ru: 'Скачать на устройство', uk: 'Завантажити на пристрій', be: 'Спампаваць на прыладу', pl: 'Pobierz na urządzenie', cs: 'Stáhnout do zařízení' },
        'Save the JSON file to your computer': {
            ru: 'Сохранить JSON-файл на компьютер',
            uk: 'Зберегти JSON-файл на комп’ютер',
            be: 'Захаваць JSON-файл на камп’ютар',
            pl: 'Zapisz plik JSON na komputer',
            cs: 'Uložit soubor JSON do počítače'
        },
        'Save to cloud': { ru: 'Сохранить в облако', uk: 'Зберегти в хмару', be: 'Захаваць у воблака', pl: 'Zapisz do chmury', cs: 'Uložit do cloudu' },
        'Store in your cloud storage (folders, groups)': {
            ru: 'Сохранить в облачное хранилище (папки, группы)',
            uk: 'Зберегти в хмарне сховище (папки, групи)',
            be: 'Захаваць в воблачнае сховішча (папкі, групы)',
            pl: 'Zapisz w chmurze (foldery, grupy)',
            cs: 'Uložit do cloudového úložiště (složky, skupiny)'
        },
        'saveRootMain': { ru: 'Главная', uk: 'Головна', be: 'Галоўная', pl: 'Główny', cs: 'Hlavní' },
        'saveRootTemp': { ru: 'Временная папка', uk: 'Тимчасова папка', be: 'Часовая папка', pl: 'Folder tymczasowy', cs: 'Dočasná složka' },
        'saveRootDocuments': { ru: 'Документы', uk: 'Документи', be: 'Дакументы', pl: 'Dokumenty', cs: 'Dokumenty' },
        'saveRootPhotos': { ru: 'Фото', uk: 'Фото', be: 'Фота', pl: 'Zdjęcia', cs: 'Fotky' },
        'saveRootPdf': { ru: 'ПДФ', uk: 'ПДФ', be: 'ПДФ', pl: 'PDF', cs: 'PDF' },
        'saveRootArchive': { ru: 'Архив', uk: 'Архів', be: 'Архіў', pl: 'Archiwum', cs: 'Archiv' },
        'No subfolders': { ru: 'Нет подпапок', uk: 'Немає підпапок', be: 'Няма падпапак', pl: 'Brak podfolderów', cs: 'Žádné podsložky' },
        'New folder name': { ru: 'Имя новой папки', uk: 'Назва нової папки', be: 'Назва новай папкі', pl: 'Nazwa nowego folderu', cs: 'Název nové složky' },
        'Create folder': { ru: 'Создать папку', uk: 'Створити папку', be: 'Стварыць папку', pl: 'Utwórz folder', cs: 'Vytvořit složku' },
        'File name:': { ru: 'Имя файла:', uk: "Назва файлу:", be: 'Назва файла:', pl: 'Nazwa pliku:', cs: 'Název souboru:' },
        'Project saved to file': { ru: 'Проект сохранён в файл', uk: 'Проєкт збережено у файл', be: 'Праект захаваны ў файл', pl: 'Projekt zapisano do pliku', cs: 'Projekt uložen do souboru' },
        'Project saved to cloud': { ru: 'Проект сохранён в облако', uk: 'Проєкт збережено в хмару', be: 'Праект захаваны ў воблака', pl: 'Projekt zapisano do chmury', cs: 'Projekt uložen do cloudu' },
        'Save project as JSON (download or cloud)': {
            ru: 'Сохранить проект как JSON (скачивание или облако)',
            uk: 'Зберегти проєкт як JSON (завантаження або хмара)',
            be: 'Захаваць праект як JSON (спампоўка або воблака)',
            pl: 'Zapisz projekt jako JSON (pobieranie lub chmura)',
            cs: 'Uložit projekt jako JSON (stažení nebo cloud)'
        },

        /* Inspector extras */
        'Remove variable': { ru: 'Удалить переменную', uk: 'Видалити змінну', be: 'Выдаліць зменную', pl: 'Usuń zmienną', cs: 'Odebrat proměnnou' },
        'Delete font': { ru: 'Удалить шрифт', uk: 'Видалити шрифт', be: 'Выдаліць шрыфт', pl: 'Usuń czcionkę', cs: 'Smazat písmo' },
        'Group': { ru: 'Группа', uk: 'Група', be: 'Група', pl: 'Grupa', cs: 'Skupina' },

        /* Shortcuts panel groups/actions */
        'General': { ru: 'Основные', uk: 'Загальні', be: 'Агульныя', pl: 'Ogólne', cs: 'Obecné' },
        'Undo': { ru: 'Отменить', uk: 'Скасувати', be: 'Адмяніць', pl: 'Cofnij', cs: 'Zpět' },
        'Copy code': { ru: 'Копировать код', uk: 'Копіювати код', be: 'Капіяваць код', pl: 'Kopiuj kod', cs: 'Kopírovat kód' },
        'Paste / import code': { ru: 'Вставить / импортировать код', uk: 'Вставити / імпортувати код', be: 'Уставіць / імпартаваць код', pl: 'Wklej / importuj kod', cs: 'Vložit / importovat kód' },
        'Delete selection': { ru: 'Удалить выделенное', uk: 'Видалити виділене', be: 'Выдаліць вылучанае', pl: 'Usuń zaznaczone', cs: 'Smazat výběr' },
        'Deselect / cancel': { ru: 'Снять выделение / отмена', uk: 'Зняти виділення / скасувати', be: 'Зняць вылучэнне / адмена', pl: 'Odznacz / anuluj', cs: 'Zrušit výběr / zrušit' },
        'Select tool': { ru: 'Инструмент выбора', uk: 'Інструмент вибору', be: 'Інструмент выбару', pl: 'Narzędzie zaznaczania', cs: 'Nástroj výběru' },
        'Text tool': { ru: 'Инструмент текста', uk: 'Інструмент тексту', be: 'Інструмент тэксту', pl: 'Narzędzie tekstu', cs: 'Nástroj textu' },
        'Paint brush': { ru: 'Кисть', uk: 'Пензель', be: 'Пэндзаль', pl: 'Pędzel', cs: 'Štětec' },
        'Image import': { ru: 'Импорт изображения', uk: 'Імпорт зображення', be: 'Імпарт выявы', pl: 'Import obrazu', cs: 'Import obrázku' },
        'Move & resize': { ru: 'Перемещение и размер', uk: 'Переміщення та розмір', be: 'Перамяшчэнне і памер', pl: 'Przenoszenie i rozmiar', cs: 'Přesun a velikost' },
        'Move by 1px': { ru: 'Переместить на 1px', uk: 'Перемістити на 1px', be: 'Перамясціць на 1px', pl: 'Przesuń o 1px', cs: 'Přesunout o 1px' },
        'Move by 10px': { ru: 'Переместить на 10px', uk: 'Перемістити на 10px', be: 'Перамясціць на 10px', pl: 'Przesuń o 10px', cs: 'Přesunout o 10px' },
        'Resize by 1px': { ru: 'Изменить размер на 1px', uk: 'Змінити розмір на 1px', be: 'Змяніць памер на 1px', pl: 'Zmień rozmiar o 1px', cs: 'Změnit velikost o 1px' },
        'Arrows': { ru: 'Стрелки', uk: 'Стрілки', be: 'Стрелкі', pl: 'Strzałki', cs: 'Šipky' },

        /* Cloud import */
        'Import from cloud': { ru: 'Импорт из облака', uk: 'Імпорт з хмари', be: 'Імпарт з воблака', pl: 'Import z chmury', cs: 'Import z cloudu' },
        'No files': { ru: 'Нет файлов', uk: 'Немає файлів', be: 'Няма файлаў', pl: 'Brak plików', cs: 'Žádné soubory' },

        /* remaining shortcuts keys/tools */
        'Del': { ru: 'Del', uk: 'Del', be: 'Del', pl: 'Del', cs: 'Del' },
        'Esc': { ru: 'Esc', uk: 'Esc', be: 'Esc', pl: 'Esc', cs: 'Esc' },
        'Tools': { ru: 'Инструменты', uk: 'Інструменти', be: 'Інструменты', pl: 'Narzędzia', cs: 'Nástroje' },
        'Ellipse': { ru: 'Эллипс', uk: 'Еліпс', be: 'Эліпс', pl: 'Elipsa', cs: 'Elipsa' },
        'Line': { ru: 'Линия', uk: 'Лінія', be: 'Лінія', pl: 'Linia', cs: 'Čára' },
        'Rectangle': { ru: 'Прямоугольник', uk: 'Прямокутник', be: 'Прамавугольнік', pl: 'Prostokąt', cs: 'Obdélník' },
    };

    function translate(text) {
        var entry = T[text];
        return entry ? (entry[lang] || null) : null;
    }

    /* Rules for strings that contain dynamic values. */
    var REGEX_RULES = [
        {
            re: /^\s*Selected layers\s*\(\s*\d+\s*\)\s*$/,
            make: function (m) { return m.replace(/Selected layers/, translate('Selected layers')); }
        },
        {
            re: /^\s*Custom\s+\d+x\d+\s*$/,
            make: function (m) { return m.replace(/^\s*Custom/, translate('Custom')); }
        },
        {
            re: /^\s*Font Preview:\s+.+$/,
            make: function (m) { return m.replace(/^\s*Font Preview:/, translate('Font Preview') + ':'); }
        }
    ];

    var ATTRS = ['data-tip', 'placeholder', 'title', 'aria-label'];
    var SKIP_TAGS = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEXTAREA: 1, CODE: 1, PRE: 1 };
    var textSources = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
    var applying = false;

    function isSkipped(el) {
        if (!el || !el.closest) return true;
        if (el.isContentEditable) return true;
        if (SKIP_TAGS[el.tagName]) return true;
        return !!(el.closest('script, style, noscript, textarea, code, pre, .ace_editor'));
    }

    function resolveText(source) {
        var replacement = translate(source);
        if (replacement != null) return replacement;
        for (var r = 0; r < REGEX_RULES.length; r++) {
            var rule = REGEX_RULES[r];
            rule.re.lastIndex = 0;
            if (rule.re.test(source)) {
                rule.re.lastIndex = 0;
                return rule.make(source);
            }
        }
        return null;
    }

    function apply(root) {
        if (!root || applying) return;
        applying = true;
        try {
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            var node;
            while ((node = walker.nextNode())) {
                var parentEl = node.parentElement;
                if (isSkipped(parentEl)) continue;
                var text = node.textContent;
                var trimmed = text.trim().replace(/\u00a0/g, ' ');
                if (!trimmed) continue;
                var source;
                if (resolveText(trimmed) != null) {
                    source = trimmed;
                    if (textSources) textSources.set(node, source);
                } else if (textSources && textSources.get(node)) {
                    source = textSources.get(node);
                } else {
                    continue;
                }
                var replacement = resolveText(source);
                if (replacement == null) continue;
                var leading = text.match(/^\s*/)[0].length;
                var trailingWs = text.match(/\s*$/)[0];
                var newText = text.slice(0, leading) + replacement + trailingWs;
                if (newText !== text) node.textContent = newText;
            }

            var scope = root.querySelectorAll ? root : document;
            var els = scope.querySelectorAll ? scope.querySelectorAll('[data-tip], [placeholder], [title], [aria-label]') : [];
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                if (isSkipped(el)) continue;
                for (var a = 0; a < ATTRS.length; a++) {
                    var attr = ATTRS[a];
                    if (!el.hasAttribute(attr)) continue;
                    var key = 'data-i18n-' + attr;
                    var current = el.getAttribute(attr);
                    var sourceAttr = el.getAttribute(key);
                    if (!sourceAttr) {
                        var trimmedAttr = (current || '').trim();
                        if (translate(trimmedAttr) == null) continue;
                        el.setAttribute(key, trimmedAttr);
                        sourceAttr = trimmedAttr;
                    }
                    var t = translate(sourceAttr);
                    if (t != null && t !== current) el.setAttribute(attr, t);
                }
            }
        } finally {
            applying = false;
            if (needsReschedule) {
                needsReschedule = false;
                schedule();
            }
        }
    }

    var timer = null;
    var needsReschedule = false;
    function schedule() {
        if (applying) {
            needsReschedule = true;
            return;
        }
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            timer = null;
            apply(document.documentElement);
        }, 30);
    }
    if (window.MutationObserver) {
        new MutationObserver(schedule).observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: ATTRS.concat(['data-tip'])
        });
    }

    document.documentElement.lang = lang;
    var titleTranslation = translate(document.title);
    if (titleTranslation) document.title = titleTranslation;

    function init() { apply(document.documentElement); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', init);

    window.__lopakaI18n = { lang: lang, apply: apply, translate: translate };
})();
