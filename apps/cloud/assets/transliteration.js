(function (global) {
  'use strict';
  const TRANSLIT_TABLES = {
    ru: {
      '`': 'ё', '~': 'Ё',
      q: 'й', w: 'ц', e: 'у', r: 'к', t: 'е', y: 'н', u: 'г', i: 'ш', o: 'щ', p: 'з',
      '[': 'х', ']': 'ъ',
      a: 'ф', s: 'ы', d: 'в', f: 'а', g: 'п', h: 'р', j: 'о', k: 'л', l: 'д', ';': 'ж',
      "'": 'э', "''": 'э',
      z: 'я', x: 'ч', c: 'с', v: 'м', b: 'и', n: 'т', m: 'ь',
      ',': 'б', '.': 'ю', '/': '.', '?': ',', '<': 'Б', '>': 'Ю',
      Q: 'Й', W: 'Ц', E: 'У', R: 'К', T: 'Е', Y: 'Н', U: 'Г', I: 'Ш', O: 'Щ', P: 'З',
      '{': 'Х', '}': 'Ъ',
      A: 'Ф', S: 'Ы', D: 'В', F: 'А', G: 'П', H: 'Р', J: 'О', K: 'Л', L: 'Д', ':': 'Ж',
      '"': 'Э', '""': 'Э',
      Z: 'Я', X: 'Ч', C: 'С', V: 'М', B: 'И', N: 'Т', M: 'Ь'
    },
    uk: {
      '`': 'ґ',
      q: 'й', w: 'ц', e: 'у', r: 'к', t: 'е', y: 'н', u: 'г', i: 'ш', o: 'щ', p: 'з',
      '[': 'х', ']': 'ї',
      a: 'ф', s: 'і', d: 'в', f: 'а', g: 'п', h: 'р', j: 'о', k: 'л', l: 'д', ';': 'ж',
      "'": 'є', "''": 'є',
      z: 'я', x: 'ч', c: 'с', v: 'м', b: 'и', n: 'т', m: 'ь',
      ',': 'б', '.': 'ю', '/': '.', '?': ',', '<': 'Б', '>': 'Ю',
      Q: 'Й', W: 'Ц', E: 'У', R: 'К', T: 'Е', Y: 'Н', U: 'Г', I: 'Ш', O: 'Щ', P: 'З',
      '{': 'Х', '}': 'Ї',
      A: 'Ф', S: 'І', D: 'В', F: 'А', G: 'П', H: 'Р', J: 'О', K: 'Л', L: 'Д', ':': 'Ж',
      '"': 'Є', '""': 'Є',
      Z: 'Я', X: 'Ч', C: 'С', V: 'М', B: 'И', N: 'Т', M: 'Ь'
    }
  };

  function getTable(lang) {
    return TRANSLIT_TABLES[lang] || null;
  }

  function transliterateChar(ch, lang) {
    const table = getTable(lang);
    if (!table) return ch;
    return table[ch] || ch;
  }

  function transliterateWord(word, lang) {
    const table = getTable(lang);
    if (!table || !word) return word;
    let result = '';
    for (let i = 0; i < word.length; i++) {
      const char = word[i];
      result += table[char] || char;
    }
    return result;
  }

  function getActiveInputLang() {
    const opt = document.querySelector('.lang-option.active');
    return (opt && opt.dataset.lang) || localStorage.getItem('preferredLang') || 'en';
  }

  function attachToInput(element, getLang) {
    if (!element || element.dataset.translitBound === '1') return;
    element.dataset.translitBound = '1';
    element.addEventListener('input', () => {
      const lang = typeof getLang === 'function' ? getLang() : getActiveInputLang();
      if (!lang || lang === 'en' || lang === 'off') return;
      const table = getTable(lang);
      if (!table) return;
      const start = element.selectionStart;
      if (!start || start < 1) return;
      const value = element.value;
      const lastChar = value[start - 1];
      const mapped = table[lastChar];
      if (!mapped || mapped === lastChar) return;
      element.value = value.slice(0, start - 1) + mapped + value.slice(start);
      element.selectionStart = element.selectionEnd = start - 1 + mapped.length;
    });
  }

  const api = {
    TABLES: TRANSLIT_TABLES,
    getTable,
    transliterateChar,
    transliterateWord,
    getActiveInputLang,
    attachToInput
  };
  global.CloudTransliteration = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
