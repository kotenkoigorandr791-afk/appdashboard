(function () {
  'use strict';
  var esc = function (s) {
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  };
  function inline(s) {
    s = esc(s);
    var codes = [];
    s = s.replace(/`([^`]+)`/g, function (m, c) { codes.push(c); return '\u0000' + (codes.length - 1) + '\u0000'; });
    s = s.replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, '<img alt="$1" src="$2">');
    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, '<a href="$2">$1</a>');
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    s = s.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    s = s.replace(/(^|[^\w*])\*([^*\n]+)\*(?!\w)/g, '$1<em>$2</em>');
    s = s.replace(/(^|[^\w_])_([^_\n]+)_(?!\w)/g, '$1<em>$2</em>');
    return s.replace(/\u0000(\d+)\u0000/g, function (m, i) { return '<code>' + codes[i] + '</code>'; });
  }
  function mdToHtml(md) {
    var lines = String(md || '').replace(/\r\n?/g, '\n').split('\n');
    var out = [];
    var i = 0;
    while (i < lines.length) {
      var l = lines[i];
      var m;
      if (/^```/.test(l)) {
        var buf = [];
        i++;
        while (i < lines.length && !/^```/.test(lines[i])) buf.push(lines[i++]);
        i++;
        out.push('<pre>' + esc(buf.join('\n')) + '</pre>');
      } else if ((m = /^(#{1,3})\s+(.*)$/.exec(l))) {
        out.push('<h' + m[1].length + '>' + inline(m[2]) + '</h' + m[1].length + '>');
        i++;
      } else if (/^(-{3,}|\*{3,})\s*$/.test(l)) {
        out.push('<hr>');
        i++;
      } else if (/^>\s?/.test(l)) {
        var q = [];
        while (i < lines.length && /^>\s?/.test(lines[i])) q.push(lines[i++].replace(/^>\s?/, ''));
        out.push('<blockquote>' + q.map(inline).join('<br>') + '</blockquote>');
      } else if (/^\s*[-*+]\s+/.test(l)) {
        var ul = [];
        while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) ul.push('<li>' + inline(lines[i++].replace(/^\s*[-*+]\s+/, '')) + '</li>');
        out.push('<ul>' + ul.join('') + '</ul>');
      } else if (/^\s*\d+\.\s+/.test(l)) {
        var ol = [];
        while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i])) ol.push('<li>' + inline(lines[i++].replace(/^\s*\d+\.\s+/, '')) + '</li>');
        out.push('<ol>' + ol.join('') + '</ol>');
      } else if (l.trim() === '') {
        i++;
      } else {
        var p = [];
        while (i < lines.length && lines[i].trim() !== '' && !/^(```|#{1,3}\s|>|\s*[-*+]\s|\s*\d+\.\s)/.test(lines[i])) p.push(inline(lines[i++]));
        out.push('<p>' + p.join('<br>') + '</p>');
      }
    }
    return out.join('');
  }
  function kids(n, ctx) {
    var s = '';
    for (var c = n.firstChild; c; c = c.nextSibling) s += node(c, ctx);
    return s;
  }
  function node(n, ctx) {
    if (n.nodeType === 3) return n.nodeValue.replace(/\u00a0/g, ' ');
    if (n.nodeType !== 1) return '';
    var t = n.tagName.toLowerCase();
    var c;
    switch (t) {
      case 'br': return '\n';
      case 'b': case 'strong': c = kids(n, ctx); return c.trim() ? '**' + c + '**' : c;
      case 'i': case 'em': c = kids(n, ctx); return c.trim() ? '_' + c + '_' : c;
      case 's': case 'strike': case 'del': c = kids(n, ctx); return c.trim() ? '~~' + c + '~~' : c;
      case 'code': return n.parentNode.tagName === 'PRE' ? n.textContent : '`' + n.textContent + '`';
      case 'a': return '[' + kids(n, ctx) + '](' + (n.getAttribute('href') || '') + ')';
      case 'img': return '![' + (n.getAttribute('alt') || '') + '](' + (n.getAttribute('src') || '') + ')';
      case 'h1': case 'h2': case 'h3': case 'h4':
        return '\n\n' + new Array(Math.min(3, +t[1]) + 1).join('#') + ' ' + kids(n, ctx).trim() + '\n\n';
      case 'hr': return '\n\n---\n\n';
      case 'pre': return '\n\n```\n' + n.textContent.replace(/\n$/, '') + '\n```\n\n';
      case 'blockquote':
        return '\n\n' + kids(n, ctx).trim().split('\n').map(function (x) { return '> ' + x; }).join('\n') + '\n\n';
      case 'ul': case 'ol':
        var idx = 0;
        var items = [];
        for (var li = n.firstChild; li; li = li.nextSibling) {
          if (li.nodeType !== 1) continue;
          idx++;
          items.push((t === 'ul' ? '* ' : idx + '. ') + kids(li, ctx).trim().replace(/\n/g, '\n  '));
        }
        return '\n\n' + items.join('\n') + '\n\n';
      case 'li': return kids(n, ctx);
      case 'div': case 'p': c = kids(n, ctx); return '\n\n' + c.trim() + '\n\n';
      default: return kids(n, ctx);
    }
  }
  function htmlToMd(root) {
    return kids(root, {}).replace(/\n{3,}/g, '\n\n').replace(/^\s+|\s+$/g, '');
  }

  var BUTTONS = [
    ['bold', 'fa-bold', 'Bold'],
    ['italic', 'fa-italic', 'Italic'],
    ['strikeThrough', 'fa-strikethrough', 'Strikethrough'],
    ['h', 'fa-header', 'Heading'],
    ['link', 'fa-link', 'Link'],
    ['quote', 'fa-quote-right', 'Quote'],
    ['insertUnorderedList', 'fa-list-ul', 'List'],
    ['insertOrderedList', 'fa-list-ol', 'Numbered list'],
    ['code', 'fa-code', 'Code']
  ];

  function enhance(editor) {
    if (editor.getAttribute('data-md-live') === '1') return;
    var write = editor.querySelector('.text-editor-write-mode');
    var textarea = write && write.querySelector('textarea');
    if (!textarea) return;
    editor.setAttribute('data-md-live', '1');
    var wrap = document.createElement('div');
    wrap.className = 'kb-md-live';
    var bar = document.createElement('div');
    bar.className = 'kb-md-live-toolbar';
    var area = document.createElement('div');
    area.className = 'kb-md-live-area markdown';
    area.contentEditable = 'true';
    area.setAttribute('data-placeholder', textarea.getAttribute('placeholder') || '');
    area.innerHTML = mdToHtml(textarea.value);
    function sync() {
      textarea.value = htmlToMd(area);
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function run(cmd) {
      area.focus();
      if (cmd === 'h') document.execCommand('formatBlock', false, 'h2');
      else if (cmd === 'quote') document.execCommand('formatBlock', false, 'blockquote');
      else if (cmd === 'code') document.execCommand('formatBlock', false, 'pre');
      else if (cmd === 'link') {
        var url = window.prompt('URL', 'https://');
        if (url) document.execCommand('createLink', false, url);
      } else document.execCommand(cmd, false, null);
      sync();
    }
    BUTTONS.forEach(function (b) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.title = b[2];
      btn.setAttribute('aria-label', b[2]);
      btn.innerHTML = '<i class="fa ' + b[1] + ' fa-fw"></i>';
      btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      btn.addEventListener('click', function () { run(b[0]); });
      bar.appendChild(btn);
    });
    area.addEventListener('input', sync);
    area.addEventListener('paste', function (e) {
      e.preventDefault();
      var txt = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, txt);
    });
    area.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey) {
        var k = e.key.toLowerCase();
        if (k === 'b' || k === 'i') { e.preventDefault(); run(k === 'b' ? 'bold' : 'italic'); }
      }
    });
    wrap.appendChild(bar);
    wrap.appendChild(area);
    editor.appendChild(wrap);
    Array.prototype.forEach.call(editor.children, function (c) {
      if (c !== wrap) c.style.display = 'none';
    });
    var form = textarea.form;
    if (form) form.addEventListener('submit', sync, true);
  }
  function scan(root) {
    var list = (root || document).querySelectorAll ? (root || document).querySelectorAll('.text-editor') : [];
    for (var i = 0; i < list.length; i++) enhance(list[i]);
  }
  window.KBMarkdownLive = { enhance: enhance, scan: scan, mdToHtml: mdToHtml, htmlToMd: htmlToMd };
  var timer = null;
  new MutationObserver(function () {
    if (timer) return;
    timer = setTimeout(function () { timer = null; scan(document); }, 30);
  }).observe(document.documentElement, { childList: true, subtree: true });
  scan(document);
})();
