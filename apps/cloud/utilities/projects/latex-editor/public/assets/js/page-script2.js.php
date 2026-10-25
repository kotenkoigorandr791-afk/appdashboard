    
    let editor;
    let listingEditor;
    let monacoReady = false;

    // ─── Touch device detection (same logic as monaco-editor.js) ─────────────
    const isTouchDevice = ('ontouchstart' in window) ||
                          (navigator.maxTouchPoints > 0) ||
                          (navigator.msMaxTouchPoints > 0);

    // ─── Portrait orientation detection ──────────────────────────────────────
    const isPortraitOrientation = window.innerHeight > window.innerWidth;

    // ─── Check if minimap should be hidden ───────────────────────────────────
    const shouldHideMinimap = isTouchDevice && isPortraitOrientation;

    // ─── Touch selection support (ported from monaco-editor.js) ──────────────
    /**
     * Enables natural finger-based text selection in Monaco on touch screens.
     *
     * Behaviour mirrors what users expect from a regular <textarea>:
     *   • Single tap          → place cursor
     *   • Double-tap          → select word under finger
     *   • Triple-tap (fast)   → select whole line
     *   • Long press (500 ms) → open context menu (copy / paste)
     *   • Drag after tap      → extend selection from tap point
     */
    function enableTouchSelection(editorInstance, container) {
      if (window.monacoTouchSelection) window.monacoTouchSelection.enable(editorInstance, container);
    }
    
    // LaTeX token provider for custom syntax highlighting
    const latexLanguageDefinition = {
      defaultToken: 'invalid',
      tokenPostfix: '.tex',
      
      brackets: [
        ['{', '}', 'delimiter.curly'],
        ['[', ']', 'delimiter.square'],
        ['(', ')', 'delimiter.parenthesis']
      ],
      
      keywords: [
        'documentclass', 'usepackage', 'begin', 'end', 'title', 'author', 'date',
        'maketitle', 'section', 'subsection', 'subsubsection', 'chapter', 'part',
        'label', 'ref', 'cite', 'pageref', 'textbf', 'textit', 'underline',
        'emph', 'footnote', 'item', 'table', 'figure', 'caption', 'centering',
        'includegraphics', 'includepdf', 'addcontentsline', 'tableofcontents',
        'newpage', 'clearpage', 'hline', 'multicolumn', 'multirow', 'hfill',
        'vfill', 'hspace', 'vspace', 'bigskip', 'medskip', 'smallskip'
      ],
      
      environments: [
        'itemize', 'enumerate', 'description', 'quote', 'quotation', 'verse',
        'center', 'flushleft', 'flushright', 'minipage', 'tabular', 'table',
        'figure', 'equation', 'align', 'gather', 'multline', 'split',
        'cases', 'matrix', 'pmatrix', 'bmatrix', 'vmatrix', 'Vmatrix',
        'theorem', 'lemma', 'proof', 'definition', 'example', 'remark'
      ],
      
      mathoperators: [
        'frac', 'sqrt', 'sum', 'int', 'prod', 'lim', 'log', 'ln', 'exp',
        'sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'arcsin', 'arccos', 'arctan',
        'sinh', 'cosh', 'tanh', 'coth', 'max', 'min', 'inf', 'sup', 'det', 'dim',
        'hom', 'ker', 'Pr'
      ],
      
      tokenizer: {
        root: [
          [/(\\documentclass|\\usepackage|\\begin|\\end|\\title|\\author|\\date|\\maketitle)/, { token: 'keyword' }],
          [/\\(section|subsection|subsubsection|chapter|part|label|ref|cite|pageref)/, { token: 'type' }],
          [/\\(textbf|textit|underline|emph|footnote|item)/, { token: 'function' }],
          [/\\(table|figure|caption|centering|includegraphics|includepdf|addcontentsline)/, { token: 'class' }],
          [/\\(hline|multicolumn|multirow|hfill|vfill|hspace|vspace|bigskip|medskip|smallskip)/, { token: 'type' }],
          [/\\(begin|end)\s*\{[^}]*\}/, { token: 'class' }],
          [/\\(itemize|enumerate|description|quote|quotation|verse|center|flushleft|flushright|minipage|tabular)/, { token: 'type' }],
          [/\\(equation|align|gather|multline|split|cases|matrix|pmatrix|bmatrix|vmatrix|Vmatrix)/, { token: 'class' }],
          [/\\(theorem|lemma|proof|definition|example|remark)/, { token: 'class' }],
          [/\\(frac|sqrt|sum|int|prod|lim|log|ln|exp|sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|sinh|cosh|tanh|coth|max|min|inf|sup|det|dim|hom|ker|Pr)/, { token: 'function' }],
          [/\\([a-zA-Z]+)/, { token: 'keyword' }],
          [/\\%/, { token: 'constant.character.escape' }],
          [/(?<!\\)%.*$/, { token: 'comment' }],
          [/\d+(\.\d+)?/, { token: 'number' }],
          [/\{|\}|\[|\]|\(|\)/, { token: 'delimiter.bracket' }],
          [/[$&]/, { token: 'operator' }],
          [/[a-zA-Z]+/, { token: 'text' }],
        ],
      }
    };
    
    // Configure Monaco: the loader, its localized built-in UI and the shared
    // editor options come from assets/monaco-shared.js, the module shared with
    // the main cloud editor.
    const shared = window.cloudMonacoShared;
    shared.loadMonaco({
      lang: <?php echo json_encode($lang); ?>,
      i18nUrl: '<?= htmlspecialchars(cloud_url('i18n/load.php')) ?>'
    }).then(function () {
      // Register LaTeX language with custom tokens
      monaco.languages.register({ id: 'latex' });
      
      monaco.languages.setMonarchTokensProvider('latex', {
        tokenizer: {
          root: [
            [/(\\documentclass|\\usepackage|\\begin|\\end|\\title|\\author|\\date|\\maketitle)/, { token: 'keyword' }],
            [/\\(section|subsection|subsubsection|chapter|part|label|ref|cite|pageref)/, { token: 'type' }],
            [/\\(textbf|textit|underline|emph|footnote|item)/, { token: 'function' }],
            [/\\(table|figure|caption|centering|includegraphics|includepdf|addcontentsline)/, { token: 'class' }],
            [/\\(hline|multicolumn|multirow|hfill|vfill|hspace|vspace|bigskip|medskip|smallskip)/, { token: 'type' }],
            [/\\(begin|end)\s*\{[^}]*\}/, { token: 'class' }],
            [/\\(itemize|enumerate|description|quote|quotation|verse|center|flushleft|flushright|minipage|tabular)/, { token: 'type' }],
            [/\\(equation|align|gather|multline|split|cases|matrix|pmatrix|bmatrix|vmatrix|Vmatrix)/, { token: 'class' }],
            [/\\(theorem|lemma|proof|definition|example|remark)/, { token: 'class' }],
            [/\\(frac|sqrt|sum|int|prod|lim|log|ln|exp|sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|sinh|cosh|tanh|coth|max|min|inf|sup|det|dim|hom|ker|Pr)/, { token: 'function' }],
            [/\\([a-zA-Z]+)/, { token: 'keyword' }],
            [/\\%/, { token: 'constant.character.escape' }],
            [/(?<!\\)%.*$/, { token: 'comment' }],
            [/\d+(\.\d+)?/, { token: 'number' }],
            [/\{|\}|\[|\]|\(|\)/, { token: 'delimiter.bracket' }],
            [/[$&]/, { token: 'operator' }],
            [/[a-zA-Z]+/, { token: 'text' }],
          ],
        }
      });
      
      // Set theme colors based on current theme
      const isDark = <?php echo $theme === 'dark' ? 'true' : 'false'; ?>;
      
      // Same palette as the main editor; only the LaTeX token colours are extra.
      shared.defineAppThemes(monaco, {
  accent: <?php echo json_encode($accentColor); ?>,
  rules: [
    { token: 'comment', foreground: '<?php echo $theme === 'light' ? '098658' : '6a9955'; ?>', fontStyle: 'italic' },
    { token: 'constant.character.escape', foreground: '<?php echo $theme === 'light' ? '0451a5' : '9cdcfe'; ?>' },
    { token: 'keyword', foreground: '<?php echo $theme === 'light' ? '800000' : 'd16969'; ?>', fontStyle: 'bold' },
    { token: 'type', foreground: '<?php echo $theme === 'light' ? '0451a5' : '9cdcfe'; ?>' },
    { token: 'function', foreground: '<?php echo $theme === 'light' ? '800000' : 'd16969'; ?>' },
    { token: 'class', foreground: '<?php echo $theme === 'light' ? 'a31515' : 'ce9178'; ?>' },
    { token: 'number', foreground: '<?php echo $theme === 'light' ? '0000ff' : '569cd6'; ?>' },
    { token: 'text', foreground: '<?php echo $theme === 'light' ? '000000' : 'ffffff'; ?>' },
    { token: 'delimiter.bracket', foreground: '<?php echo $theme === 'light' ? '000000' : 'd4d4d4'; ?>' },
    { token: 'operator', foreground: '<?php echo $theme === 'light' ? '000000' : 'd4d4d4'; ?>' },
  ]
});
      
      // Create editor
      const initialContent = <?php echo json_encode($editorContent); ?>;
      const monacoContainer = document.getElementById('monaco-editor');
      
      editor = monaco.editor.create(monacoContainer, {
        value: initialContent,
        language: 'latex',
        theme: isDark ? 'custom-dark' : 'custom-light',
        automaticLayout: true,

        // ── Touch-adaptive settings ──────────────────────────────────────────
        // Larger font & hidden line numbers make tap targets comfortable on phones
        fontSize:    isTouchDevice ? 18 : 16,
        lineNumbers: 'on',
        // Increase the left margin so fingers don't accidentally hit the gutter
        lineDecorationsWidth: isTouchDevice ? 20 : 10,
        // Extra space between lines so individual lines are easier to tap
        lineHeight: isTouchDevice ? 28 : 0,    // 0 = Monaco default
        // Disable drag-and-drop on touch – it conflicts with selection gesture
        dragAndDrop: !isTouchDevice,
        // Touch scrolling sensitivity
        mouseWheelScrollSensitivity: isTouchDevice ? 3 : 1,
        fastScrollSensitivity:       isTouchDevice ? 4 : 2,
        // ────────────────────────────────────────────────────────────────────

        minimap: { enabled: !shouldHideMinimap, size: 'fill', showSlider: 'always' },
        scrollBeyondLastLine: true,
        wordWrap: 'on',
        wrappingIndent: 'indent',
        folding: true,
        foldingStrategy: 'auto',
        showFoldingControls: 'always',
        renderLineHighlight: 'all',
        cursorBlinking: 'smooth',
        cursorSmoothCaretAnimation: 'on',
        smoothScrolling: true,
        formatOnPaste: true,
        formatOnType: true,
        autoClosingBrackets: 'always',
        autoClosingQuotes: 'always',
        autoIndent: 'full',
        tabSize: 2,
        mouseWheelZoom: false,
        insertSpaces: true,
        selectionClipboard: true,
        wordBasedSuggestions: false,
        renderWhitespace: 'selection',
        bracketPairColorization: { enabled: true },
        guides: { bracketPairs: true, indentation: true },
        scrollbar: {
          vertical: 'hidden',
          horizontal: 'hidden',
          alwaysConsumeMouseWheel: false,
          handleMouseWheel: true,
          verticalHasArrows: false,
          horizontalHasArrows: false,
          verticalScrollbarSize: 0,
          horizontalScrollbarSize: 0,
          useShadows: true
        },
        overviewRulerLanes: 0,
        hideCursorInOverviewRuler: true,
        overviewRulerBorder: false
      });

      enableTouchSelection(editor, monacoContainer);

      // Realtime collaboration via the shared cloud-collab-ws tunnel
      const latexFilePath = '<?php echo htmlspecialchars($filePath, ENT_QUOTES); ?>';
      const latexRoot = '<?php echo htmlspecialchars($root, ENT_QUOTES); ?>';
      const latexUser = '<?php echo htmlspecialchars($currentUser, ENT_QUOTES); ?>';
      let suppressCollabContent = false;
      if (window.cloudIframeCollab) {
        window.cloudIframeCollab.enable({
          editor: editor,
          container: monacoContainer,
          filePath: latexFilePath,
          root: latexRoot,
          user: latexUser,
          userName: latexUser,
          getLocalContent: function() { return editor.getValue(); },
          onRemoteContent: function(content) {
            if (typeof content !== 'string') return;
            suppressCollabContent = true;
            try {
              var viewState = editor.saveViewState();
              var pos = editor.getPosition();
              editor.setValue(content);
              if (viewState) editor.restoreViewState(viewState);
              if (pos) { try { editor.setPosition(pos); } catch (e) {} }
              lastContent = content;
            } finally {
              suppressCollabContent = false;
            }
          },
          onCompiling: function(msg) {
            setStatus((msg && msg.name ? msg.name : 'user') + ': ' + (t['compiling'] || 'compiling...'), 'status-compiling');
          },
          onRemoteCompile: function(msg) {
            // Another user compiled the document - refresh the PDF preview
            recompilePreviewAfterRemoteCompile();
          }
        });
      }
      if (isTouchDevice) {
        editor.addAction({
          id: 'touch-select-all',
          label: 'Select All',
          contextMenuGroupId: '9_cutcopypaste',
          contextMenuOrder: 1,
          run: function(ed) {
            const model = ed.getModel();
            const lastLine   = model.getLineCount();
            const lastColumn = model.getLineMaxColumn(lastLine);
            ed.setSelection(new monaco.Range(1, 1, lastLine, lastColumn));
            ed.revealLine(1);
          }
        });

        editor.addAction({
          id: 'touch-select-word',
          label: 'Select Word',
          contextMenuGroupId: '9_cutcopypaste',
          contextMenuOrder: 2,
          run: function(ed) {
            const pos  = ed.getPosition();
            const word = ed.getModel().getWordAtPosition(pos);
            if (word) {
              ed.setSelection(new monaco.Range(
                pos.lineNumber, word.startColumn,
                pos.lineNumber, word.endColumn
              ));
            }
          }
        });

        editor.addAction({
          id: 'touch-select-line',
          label: 'Select Line',
          contextMenuGroupId: '9_cutcopypaste',
          contextMenuOrder: 3,
          run: function(ed) {
            const pos  = ed.getPosition();
            const len  = ed.getModel().getLineMaxColumn(pos.lineNumber);
            ed.setSelection(new monaco.Range(pos.lineNumber, 1, pos.lineNumber, len));
          }
        });
      }
      
      monacoReady = true;
      if (window.attachCloudIframeMonacoAi) window.attachCloudIframeMonacoAi(editor);
      window.cloudIframeMonacoAiSave = saveFile;

      
      // Setup keyboard shortcuts
      editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function() {
        saveFile();
      });
      
      editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, function() {
        compileLatex(false);
      });
      
      editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Slash, function() {
        toggleComment();
      });
      
      editor.onDidChangeModelContent(function() {
          updateUndoRedoButtons();
      });

      editor.onDidChangeModel(function() {
          updateUndoRedoButtons();
      }); 
      setTimeout(updateUndoRedoButtons, 500);

      editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyD, function() {
        deleteLine();
      });
      
      editor.addAction({
        id: 'select-all-custom',
        label: 'Select All',           // or t['select_all'] if you add a translation
        keybindings: [
          monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyA
        ],
        contextMenuGroupId: 'navigation', // group in the context menu
        contextMenuOrder: 0,              // order - first
        run: function(ed) {
          const model = ed.getModel();
          const lastLine = model.getLineCount();
          const lastColumn = model.getLineMaxColumn(lastLine);
          ed.setSelection(new monaco.Range(1, 1, lastLine, lastColumn));
          ed.focus();
        }
      });

      // Update formatting buttons state on selection change
      editor.onDidChangeCursorSelection(function(e) {
        updateFormattingButtonsState();
      });
      
      // Setup auto-save
      setupAutoSave();

      setupStatusBarReset();
      
      // Same gutter, folding and transliteration rules as the main editor.
      editor.updateOptions(shared.standardEditorOptions());
      shared.installTransliteration({
        editor: editor,
        container: monacoContainer,
        monaco: monaco,
        getLang: function () { return shared.resolveLang(<?php echo json_encode($lang); ?>); }
      });

      monacoReady = true;
    }).catch(function (e) {
      console.error('Monaco failed to load:', e);
    });
    
    let isResizing = false;
    let startX, startY, startWidth, startHeight;
    let panelSplitRatio = 0.5;
    let relayoutTimer = null;
    const RESIZER_THICKNESS = 5;
    const resizer = document.getElementById('resizer');
    const editorPanel = document.getElementById('editor-panel');
    const pdfPanel = document.getElementById('pdf-panel');
    const main = document.getElementById('main');
    const isMobile = <?php echo $isMobile ? 'true' : 'false'; ?>;
    const isTablet = <?php echo $isTablet ? 'true' : 'false'; ?>;
    const t = <?php echo json_encode($t); ?>;
    const currentLang = '<?php echo $lang; ?>';
    function isPortraitLayout() {
      return (isMobile && !isTablet) || window.matchMedia('(orientation: portrait)').matches;
    }
    
    // Translation related variables
    let currentSelectedText = '';
    let lastTranslationResult = '';
    let lastTranslationParams = { sourceLang: 'auto', targetLang: 'ru' };
    
    // Store last translation promise to show status when window closed
    let lastTranslationPromise = null;

    function showNotification(title, message, type = 'info', duration = 5000) {
      // Show notifications for all important operations
      const container = document.getElementById('notification-container');
      
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      
      const iconSvg = type === 'success' ? 
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l2 2 4-4"/></svg>' :
        type === 'error' ?
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>' :
        type === 'warning' ?
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="1"/></svg>' :
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><circle cx="12" cy="8" r="1"/></svg>';
      
      notification.innerHTML = `
        <div class="notification-icon">${iconSvg}</div>
        <div class="notification-content">
          <div class="notification-title">${title}</div>
          <div class="notification-message">${message}</div>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      `;
      
      container.appendChild(notification);
      
      setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
      }, duration);
    }
    
    let isFullscreen = false;
    
    let tableData = [];
    let selectedCells = [];
    let mergedCells = [];
    let editingCell = null;
    
    function initTableEditor() {
      generateTablePreview();
      document.querySelectorAll('.table-editor-tab').forEach(tab => {
        tab.addEventListener('click', function() {
          const tabName = this.getAttribute('data-tab');
          switchTab(tabName);
        });
      });
      document.getElementById('add-row').addEventListener('click', addTableRow);
      document.getElementById('add-column').addEventListener('click', addTableColumn);
      document.getElementById('remove-row').addEventListener('click', removeTableRow);
      document.getElementById('remove-column').addEventListener('click', removeTableColumn);
      document.getElementById('merge-cells').addEventListener('click', mergeSelectedCells);
      document.getElementById('split-cells').addEventListener('click', splitSelectedCells);
      document.getElementById('generate-latex').addEventListener('click', generateLatexCode);
      document.getElementById('insert-latex').addEventListener('click', insertTableLatex);
      document.getElementById('table-rows').addEventListener('change', generateTablePreview);
      document.getElementById('table-cols').addEventListener('change', generateTablePreview);
      document.getElementById('table-border').addEventListener('change', generateTablePreview);
    }
    
    function switchTab(tabName) {
      document.querySelectorAll('.table-editor-content').forEach(content => {
        content.classList.remove('active');
      });
      document.getElementById(tabName + '-tab').classList.add('active');
      document.querySelectorAll('.table-editor-tab').forEach(tab => {
        tab.classList.remove('active');
      });
      document.querySelector(`.table-editor-tab[data-tab="${tabName}"]`).classList.add('active');
      if (tabName === 'latex') generateLatexCode();
    }
    
    function generateTablePreview() {
      const rows = parseInt(document.getElementById('table-rows').value) || 3;
      const cols = parseInt(document.getElementById('table-cols').value) || 3;
      if (tableData.length === 0) {
        tableData = [];
        for (let i = 0; i < rows; i++) {
          tableData[i] = [];
          for (let j = 0; j < cols; j++) {
            tableData[i][j] = `Cell ${i+1}-${j+1}`;
          }
        }
      } else {
        const newData = [];
        for (let i = 0; i < rows; i++) {
          newData[i] = [];
          for (let j = 0; j < cols; j++) {
            newData[i][j] = (tableData[i] && tableData[i][j]) ? tableData[i][j] : `Cell ${i+1}-${j+1}`;
          }
        }
        tableData = newData;
      }
      const previewTable = document.getElementById('table-preview');
      previewTable.innerHTML = '';
      const headerRow = document.createElement('tr');
      headerRow.innerHTML = '<th></th>';
      for (let j = 0; j < cols; j++) {
        const th = document.createElement('th');
        th.textContent = String.fromCharCode(65 + j);
        headerRow.appendChild(th);
      }
      previewTable.appendChild(headerRow);
      for (let i = 0; i < rows; i++) {
        const tr = document.createElement('tr');
        const rowHeader = document.createElement('th');
        rowHeader.textContent = i + 1;
        tr.appendChild(rowHeader);
        for (let j = 0; j < cols; j++) {
          const td = document.createElement('td');
          td.textContent = tableData[i][j];
          td.setAttribute('data-row', i);
          td.setAttribute('data-col', j);
          const isMerged = mergedCells.some(cell => 
            cell.startRow <= i && cell.endRow >= i && 
            cell.startCol <= j && cell.endCol >= j
          );
          if (isMerged) {
            td.classList.add('merged');
            const mergedCell = mergedCells.find(cell => 
              cell.startRow <= i && cell.endRow >= i && 
              cell.startCol <= j && cell.endCol >= j
            );
            if (i !== mergedCell.startRow || j !== mergedCell.startCol) {
              td.style.display = 'none';
            } else {
              td.colSpan = mergedCell.endCol - mergedCell.startCol + 1;
              td.rowSpan = mergedCell.endRow - mergedCell.startRow + 1;
            }
          }
          td.addEventListener('click', handleCellClick);
          td.addEventListener('dblclick', handleCellDoubleClick);
          tr.appendChild(td);
        }
        previewTable.appendChild(tr);
      }
      updateCellSelection();
    }
    
    function handleCellClick(e) {
      const cell = e.target;
      const row = parseInt(cell.getAttribute('data-row'));
      const col = parseInt(cell.getAttribute('data-col'));
      const isHidden = mergedCells.some(cell => 
        cell.startRow <= row && cell.endRow >= row && 
        cell.startCol <= col && cell.endCol >= col &&
        (row !== cell.startRow || col !== cell.startCol)
      );
      if (isHidden) return;
      if (e.shiftKey && selectedCells.length > 0) {
        const firstCell = selectedCells[0];
        const startRow = Math.min(firstCell.row, row);
        const endRow = Math.max(firstCell.row, row);
        const startCol = Math.min(firstCell.col, col);
        const endCol = Math.max(firstCell.col, col);
        selectedCells = [];
        for (let i = startRow; i <= endRow; i++) {
          for (let j = startCol; j <= endCol; j++) {
            const isCellHidden = mergedCells.some(cell => 
              cell.startRow <= i && cell.endRow >= i && 
              cell.startCol <= j && cell.endCol >= j &&
              (i !== cell.startRow || j !== cell.startCol)
            );
            if (!isCellHidden) selectedCells.push({ row: i, col: j });
          }
        }
      } else selectedCells = [{ row, col }];
      updateCellSelection();
    }
    
    function handleCellDoubleClick(e) {
      const cell = e.target;
      const row = parseInt(cell.getAttribute('data-row'));
      const col = parseInt(cell.getAttribute('data-col'));
      const isHidden = mergedCells.some(cell => 
        cell.startRow <= row && cell.endRow >= row && 
        cell.startCol <= col && cell.endCol >= col &&
        (row !== cell.startRow || col !== cell.startCol)
      );
      if (isHidden) return;
      if (editingCell) finishCellEditing();
      editingCell = { row, col, element: cell };
      cell.classList.add('editing');
      const currentText = tableData[row][col];
      const input = document.createElement('input');
      input.type = 'text';
      input.value = currentText;
      input.style.width = '100%';
      input.style.border = 'none';
      input.style.background = 'transparent';
      input.style.textAlign = 'center';
      cell.innerHTML = '';
      cell.appendChild(input);
      input.focus();
      input.select();
      input.addEventListener('blur', finishCellEditing);
      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') finishCellEditing();
        else if (e.key === 'Escape') cancelCellEditing();
      });
    }
    
    function finishCellEditing() {
      if (!editingCell) return;
      const { row, col, element } = editingCell;
      const input = element.querySelector('input');
      const newValue = input ? input.value : '';
      tableData[row][col] = newValue;
      element.textContent = newValue;
      element.classList.remove('editing');
      editingCell = null;
    }
    
    function cancelCellEditing() {
      if (!editingCell) return;
      const { row, col, element } = editingCell;
      element.textContent = tableData[row][col];
      element.classList.remove('editing');
      editingCell = null;
    }
    
    function updateCellSelection() {
      document.querySelectorAll('#table-preview td').forEach(td => {
        td.classList.remove('selected');
      });
      selectedCells.forEach(cell => {
        const selector = `td[data-row="${cell.row}"][data-col="${cell.col}"]`;
        const td = document.querySelector(selector);
        if (td && td.style.display !== 'none') td.classList.add('selected');
      });
    }
    
    function addTableRow() {
      const rows = parseInt(document.getElementById('table-rows').value) || 3;
      document.getElementById('table-rows').value = rows + 1;
      generateTablePreview();
    }
    
    function addTableColumn() {
      const cols = parseInt(document.getElementById('table-cols').value) || 3;
      document.getElementById('table-cols').value = cols + 1;
      generateTablePreview();
    }
    
    function removeTableRow() {
      const rows = parseInt(document.getElementById('table-rows').value) || 3;
      if (rows > 1) {
        document.getElementById('table-rows').value = rows - 1;
        generateTablePreview();
      }
    }
    
    function removeTableColumn() {
      const cols = parseInt(document.getElementById('table-cols').value) || 3;
      if (cols > 1) {
        document.getElementById('table-cols').value = cols - 1;
        generateTablePreview();
      }
    }
    
    function mergeSelectedCells() {
      if (selectedCells.length < 2) {
        return;
      }
      const rows = selectedCells.map(cell => cell.row);
      const cols = selectedCells.map(cell => cell.col);
      const startRow = Math.min(...rows);
      const endRow = Math.max(...rows);
      const startCol = Math.min(...cols);
      const endCol = Math.max(...cols);
      for (const mergedCell of mergedCells) {
        if ((startRow <= mergedCell.endRow && endRow >= mergedCell.startRow) &&
            (startCol <= mergedCell.endCol && endCol >= mergedCell.startCol)) {
          return;
        }
      }
      mergedCells.push({ startRow, endRow, startCol, endCol });
      let mergedContent = '';
      for (let i = startRow; i <= endRow; i++) {
        for (let j = startCol; j <= endCol; j++) {
          if (tableData[i] && tableData[i][j]) {
            if (mergedContent) mergedContent += ' ';
            mergedContent += tableData[i][j];
          }
        }
      }
      tableData[startRow][startCol] = mergedContent || 'Merged Cell';
      generateTablePreview();
    }
    
    function splitSelectedCells() {
      if (selectedCells.length !== 1) {
        return;
      }
      const cell = selectedCells[0];
      const mergedIndex = mergedCells.findIndex(mergedCell => 
        mergedCell.startRow <= cell.row && mergedCell.endRow >= cell.row &&
        mergedCell.startCol <= cell.col && mergedCell.endCol >= cell.col
      );
      if (mergedIndex === -1) {
        return;
      }
      mergedCells.splice(mergedIndex, 1);
      generateTablePreview();
    }
    
    function generateLatexCode() {
      const rows = parseInt(document.getElementById('table-rows').value) || 3;
      const cols = parseInt(document.getElementById('table-cols').value) || 3;
      const borderType = document.getElementById('table-border').value;
      const caption = document.getElementById('table-caption').value;
      const label = document.getElementById('table-label').value;
      let latexCode = '';
      if (caption || label) {
        latexCode += '\\begin{table}[h]\n';
        latexCode += '  \\centering\n';
      }
      let columnFormat = '';
      if (borderType === 'none') columnFormat = 'c'.repeat(cols);
      else columnFormat = '|' + 'c|'.repeat(cols);
      latexCode += `  \\begin{tabular}{${columnFormat}}\n`;
      if (borderType !== 'none') latexCode += '    \\hline\n';
      for (let i = 0; i < rows; i++) {
        let rowContent = '    ';
        let colIndex = 0;
        while (colIndex < cols) {
          const isHidden = mergedCells.some(cell => 
            cell.startRow <= i && cell.endRow >= i && 
            cell.startCol <= colIndex && cell.endCol >= colIndex &&
            (i !== cell.startRow || colIndex !== cell.startCol)
          );
          if (isHidden) { colIndex++; continue; }
          const cellData = tableData[i] && tableData[i][colIndex] ? tableData[i][colIndex] : `Cell ${i+1}-${colIndex+1}`;
          const mergedCell = mergedCells.find(cell => cell.startRow === i && cell.startCol === colIndex);
          if (mergedCell) {
            const multirow = mergedCell.endRow - mergedCell.startRow + 1;
            const multicol = mergedCell.endCol - mergedCell.startCol + 1;
            if (multirow > 1 && multicol > 1) rowContent += `\\multicolumn{${multicol}}{|c|}{\\multirow{${multirow}}{*}{${cellData}}}`;
            else if (multirow > 1) rowContent += `\\multirow{${multirow}}{*}{${cellData}}`;
            else if (multicol > 1) rowContent += `\\multicolumn{${multicol}}{|c|}{${cellData}}`;
            else rowContent += cellData;
            colIndex = mergedCell.endCol + 1;
          } else {
            rowContent += cellData;
            colIndex++;
          }
          if (colIndex < cols) {
            const nextCellHidden = mergedCells.some(cell => 
              cell.startRow <= i && cell.endRow >= i && 
              cell.startCol <= colIndex && cell.endCol >= colIndex &&
              (i !== cell.startRow || colIndex !== cell.startCol)
            );
            if (!nextCellHidden) rowContent += ' & ';
          }
        }
        rowContent += ' \\\\\n';
        if (borderType !== 'none') rowContent += '    \\hline\n';
        latexCode += rowContent;
      }
      latexCode += '  \\end{tabular}\n';
      if (caption) latexCode += `  \\caption{${caption}}\n`;
      if (label) latexCode += `  \\label{${label}}\n`;
      if (caption || label) latexCode += '\\end{table}';
      if (borderType === 'thick') {
        latexCode = '\\setlength{\\arrayrulewidth}{1.5pt}\n' + latexCode + '\n\\setlength{\\arrayrulewidth}{0.4pt}';
      }
      document.getElementById('latex-preview').textContent = latexCode;
      switchTab('latex');
    }
    
    function insertTableLatex() {
      const latexCode = document.getElementById('latex-preview').textContent;
      if (editor) {
        const selection = editor.getSelection();
        const id = { major: 1, minor: 1 };
        const text = latexCode;
        const op = {
          identifier: id,
          range: selection,
          text: text,
          forceMoveMarkers: true
        };
        editor.executeEdits('insert-table', [op]);
        editor.focus();
      }
      closeModal('table-modal');
    }
    
    let autoSaveTimer = null;
    let lastContent = <?php echo json_encode($editorContent); ?>;
    
    function setupAutoSave() {
      if (!editor) return;
      
      editor.onDidChangeModelContent(function() {
        const currentContent = editor.getValue();
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
          if (currentContent !== lastContent) {
            autoSaveFile(currentContent);
            lastContent = currentContent;
          }
        }, 15000);
      });
    }
    
    function autoSaveFile(content) {
      if (!content.trim()) return;
      const formData = new FormData();
      formData.append('autosave', 'true');
      formData.append('content', content);
      fetch('', { method: 'POST', body: formData })
      .then(response => response.json())
      .then(result => {
        if (result.success) notifyParentSaved();
      })
      .catch(error => {
        // Auto-save error - silent
      });
    }
    
    function getMainLayoutSize() {
      if (!main) return 0;
      if (isPortraitLayout()) {
        if (isMobile && !isTablet) {
          const footer = document.getElementById('mobile-footer');
          const header = document.getElementById('mobile-header');
          const footerH = footer && getComputedStyle(footer).display !== 'none' ? footer.offsetHeight : 0;
          const headerH = header && getComputedStyle(header).display !== 'none' ? header.offsetHeight : 0;
          return Math.max(0, window.innerHeight - footerH - headerH);
        }
        return main.offsetHeight;
      }
      return main.offsetWidth;
    }

    function updatePanelSplitRatio() {
      if (!main || !editorPanel) return;
      const total = getMainLayoutSize();
      if (total <= 0) return;
      const editorSize = isPortraitLayout() ? editorPanel.offsetHeight : editorPanel.offsetWidth;
      panelSplitRatio = Math.max(0.15, Math.min(0.85, editorSize / total));
    }

    function applyPanelLayoutFromRatio() {
      if (!main || !editorPanel || !pdfPanel || isFullscreen) return;
      if (isPortraitLayout()) {
        const total = Math.max(0, getMainLayoutSize() - RESIZER_THICKNESS);
        if (total < 220) return;
        let editorH = Math.round(total * panelSplitRatio);
        editorH = Math.max(100, Math.min(total - 100, editorH));
        const pdfH = Math.max(100, total - editorH);
        editorPanel.style.width = '100%';
        pdfPanel.style.width = '100%';
        editorPanel.style.flex = '0 0 auto';
        pdfPanel.style.flex = '0 0 auto';
        editorPanel.style.height = editorH + 'px';
        pdfPanel.style.height = pdfH + 'px';
      } else {
        const total = Math.max(0, main.offsetWidth - RESIZER_THICKNESS);
        if (total < 620) return;
        let editorW = Math.round(total * panelSplitRatio);
        editorW = Math.max(300, Math.min(total - 300, editorW));
        const pdfW = Math.max(300, total - editorW);
        editorPanel.style.height = '100%';
        pdfPanel.style.height = '100%';
        editorPanel.style.width = editorW + 'px';
        pdfPanel.style.width = pdfW + 'px';
      }
    }

    function layoutMonacoEditors() {
      if (editor) editor.layout();
      if (listingEditor) listingEditor.layout();
    }

    function relayoutLatexEditor() {
      applyPanelLayoutFromRatio();
      layoutMonacoEditors();
      updateMinimapVisibility();
    }

    function scheduleRelayout() {
      if (relayoutTimer) clearTimeout(relayoutTimer);
      relayoutTimer = setTimeout(relayoutLatexEditor, 50);
    }

    function startResize(e) {
      if (e.target && e.target.closest && e.target.closest('.rz-tools')) return;
      isResizing = true;
      if (isPortraitLayout()) {
        startY = e.clientY || e.touches[0].clientY;
        startHeight = editorPanel.offsetHeight;
      } else {
        startX = e.clientX || e.touches[0].clientX;
        startWidth = editorPanel.offsetWidth;
      }
      document.addEventListener('mousemove', resize);
      document.addEventListener('touchmove', resize, { passive: false });
      document.addEventListener('mouseup', stopResize);
      document.addEventListener('touchend', stopResize);
      e.preventDefault();
    }

    window.addEventListener('resize', scheduleRelayout);
    window.matchMedia('(orientation: portrait)').addEventListener('change', scheduleRelayout);
    if (main && typeof ResizeObserver !== 'undefined') {
      const mainResizeObserver = new ResizeObserver(scheduleRelayout);
      mainResizeObserver.observe(main);
    }

    // Update minimap visibility based on orientation
    function updateMinimapVisibility() {
        if (!editor) return;
        const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
        const isPortrait = window.innerHeight > window.innerWidth;
        const shouldHide = isTouch && isPortrait;
        editor.updateOptions({
            minimap: {
                enabled: !shouldHide,
                size: 'fill',
                showSlider: 'always'
            }
        });
    }

    window.addEventListener('load', function() {
        setTimeout(function() {
            updatePanelSplitRatio();
            relayoutLatexEditor();
        }, 100);
    });
    
    function resize(e) {
      if (!isResizing) return;
      if (isPortraitLayout()) {
        const clientY = e.clientY || (e.touches && e.touches[0].clientY);
        if (!clientY) return;
        const dy = clientY - startY;
        const newEditorHeight = Math.max(100, startHeight + dy);
        const totalHeight = getMainLayoutSize();
        const newPdfHeight = totalHeight - newEditorHeight - RESIZER_THICKNESS;
        if (newEditorHeight > 100 && newPdfHeight > 100) {
          editorPanel.style.height = newEditorHeight + 'px';
          pdfPanel.style.height = newPdfHeight + 'px';
          layoutMonacoEditors();
        }
      } else {
        const clientX = e.clientX || (e.touches && e.touches[0].clientX);
        if (!clientX) return;
        const dx = clientX - startX;
        const newEditorWidth = Math.max(300, startWidth + dx);
        const totalWidth = main.offsetWidth;
        const newPdfWidth = totalWidth - newEditorWidth - RESIZER_THICKNESS;
        if (newEditorWidth > 300 && newPdfWidth > 300) {
          editorPanel.style.width = newEditorWidth + 'px';
          pdfPanel.style.width = newPdfWidth + 'px';
          layoutMonacoEditors();
        }
      }
    }
    
    function stopResize() {
      isResizing = false;
      document.removeEventListener('mousemove', resize);
      document.removeEventListener('touchmove', resize);
      document.removeEventListener('mouseup', stopResize);
      document.removeEventListener('touchend', stopResize);
      updatePanelSplitRatio();
      scheduleRelayout();
    }
    
    function setStatus(text, className) {
      const indicator = document.getElementById('status-indicator');
      const mobileIndicator = document.getElementById('mobile-status');
      const mobileCompileBtn = document.getElementById('mobile-compile-btn');
      
      // If there are active timers, cancel them
      if (window.statusHideTimer) {
        clearTimeout(window.statusHideTimer);
        window.statusHideTimer = null;
      }
      if (window.statusGrayTimer) {
        clearTimeout(window.statusGrayTimer);
        window.statusGrayTimer = null;
      }
      
      // Determine colors depending on the theme
      const isDark = document.body.classList.contains('fullscreen-editor') ? 
        <?php echo $theme === 'dark' ? 'true' : 'false'; ?> : 
        <?php echo $theme === 'dark' ? 'true' : 'false'; ?>;
      
      const normalColor = isDark ? '#cccccc' : '#333333';
      const successColor = '#32cd32';
      const errorColor = '#ff6b6b';
      const warningColor = '#c89600';
      
      if (indicator) {
        indicator.textContent = text;
        indicator.className = className + " tb-pin";
        indicator.style.opacity = '1';
        indicator.style.transition = 'opacity 0.5s, color 0.5s';
        
        // Set the color based on the status class
        if (className === 'status-ok') {
          indicator.style.color = successColor;
        } else if (className === 'status-error') {
          indicator.style.color = errorColor;
        } else if (className === 'status-compiling') {
          indicator.style.color = warningColor;
        } else {
          indicator.style.color = normalColor;
        }
        
        // Timer to change the color to gray after 5 seconds
        window.statusGrayTimer = setTimeout(() => {
          indicator.style.color = '#888';
        }, 5000);
        
        // Timer to hide after 30 seconds
        window.statusHideTimer = setTimeout(() => {
          indicator.style.opacity = '0';
        }, 30000);
      }
      
      if (mobileIndicator) {
        mobileIndicator.textContent = text;
        mobileIndicator.className = className;
        mobileIndicator.style.opacity = '1';
        mobileIndicator.style.transition = 'opacity 0.5s, color 0.5s';
        
        // Set the color based on the status class
        if (className === 'status-ok') {
          mobileIndicator.style.color = successColor;
        } else if (className === 'status-error') {
          mobileIndicator.style.color = errorColor;
        } else if (className === 'status-compiling') {
          mobileIndicator.style.color = warningColor;
        } else {
          mobileIndicator.style.color = normalColor;
        }
        
        // Timer to change the color to gray after 5 seconds
        setTimeout(() => {
          mobileIndicator.style.color = '#888';
        }, 5000);
        
        // Timer to hide after 30 seconds
        setTimeout(() => {
          mobileIndicator.style.opacity = '0';
        }, 30000);
      }
      
      // Update the compile button state on mobile devices
      if (isMobile && !isTablet && mobileCompileBtn) {
        mobileCompileBtn.classList.remove('status-compiling', 'status-ok', 'status-error');
        mobileCompileBtn.classList.add(className);
        
        const statusIndicator = mobileCompileBtn.querySelector('.btn-status-indicator');
        if (statusIndicator) {
          if (className === 'status-compiling') {
            statusIndicator.textContent = '⟳';
          } else if (className === 'status-ok') {
            statusIndicator.textContent = '✓';
          } else if (className === 'status-error') {
            statusIndicator.textContent = '✗';
          } else {
            statusIndicator.textContent = '';
          }
        }
      }
    }
    function resetStatusColor() {
      const indicator = document.getElementById('status-indicator');
      const mobileIndicator = document.getElementById('mobile-status');
      
      const isDark = <?php echo $theme === 'dark' ? 'true' : 'false'; ?>;
      const normalColor = isDark ? '#cccccc' : '#333333';
      
      if (indicator) {
        // Cancel the current timers
        if (window.statusGrayTimer) {
          clearTimeout(window.statusGrayTimer);
          window.statusGrayTimer = null;
        }
        if (window.statusHideTimer) {
          clearTimeout(window.statusHideTimer);
          window.statusHideTimer = null;
        }
        
        // Restore color and transparency
        indicator.style.color = normalColor;
        indicator.style.opacity = '1';
        
        // Restart the timers
        window.statusGrayTimer = setTimeout(() => {
          indicator.style.color = '#888';
        }, 5000);
        
        window.statusHideTimer = setTimeout(() => {
          indicator.style.opacity = '0';
        }, 30000);
      }
      
      if (mobileIndicator) {
        mobileIndicator.style.color = normalColor;
        mobileIndicator.style.opacity = '1';
      }
    }
        
    // Add this function to the initialization
    function setupStatusBarReset() {
      // Reset on editor clicks
      if (editor) {
        editor.onDidChangeCursorSelection(function() {
          resetStatusColor();
        });
        
        editor.onDidChangeModelContent(function() {
          clearTimeout(window.resetTimer);
          window.resetTimer = setTimeout(resetStatusColor, 500);
        });
      }
      
      // Reset on clicks on the main buttons
      document.getElementById('compile-btn')?.addEventListener('click', function() {
        // setStatus will be called separately, do not reset here
      });
      
      document.getElementById('save-pdf-btn')?.addEventListener('click', function() {
        resetStatusColor();
      });
    }



    // Re-fetch and re-render the compiled PDF (viewer side of a remote compile)
    function recompilePreviewAfterRemoteCompile() {
      const formData = new FormData();
      formData.append('latex', editor ? editor.getValue() : '');
      setStatus(t['compiling'], 'status-compiling');
      return fetch('', { method: 'POST', body: formData })
        .then(async response => {
          const contentType = response.headers.get('content-type') || '';
          if (contentType.includes('application/pdf')) { window.__synctexId = response.headers.get('X-Synctex-Id') || ''; return { kind: 'pdf', blob: await response.blob() }; }
          throw new Error('no pdf');
        })
        .then(result => {
          if (result.kind === 'pdf') {
            displayPdf(result.blob);
            setStatus(t['success'], 'status-ok');
          }
        })
        .catch(() => { setStatus(t['error'], 'status-error'); });
    }
    function compileLatex(savePdf = false) {
        const runCompile = () => {
        const latexContent = editor ? editor.getValue() : '';
        setStatus(t['compiling'], 'status-compiling');
        if (window.cloudIframeCollab && window.cloudIframeCollab.getSession()) window.cloudIframeCollab.notifyLatexCompiling();
        const formData = new FormData();
        formData.append('latex', latexContent);
        if (savePdf) formData.append('save_pdf', 'true');
        return fetch('', { method: 'POST', body: formData })
        .then(async response => {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/pdf')) {
                window.__synctexId = response.headers.get('X-Synctex-Id') || '';
                return { kind: 'pdf', blob: await response.blob() };
            }
            if (contentType.includes('application/json')) {
                const data = await response.json();
                if (data.success === false) {
                    throw new Error(data.error || t['compilation_failed'] || 'Compilation failed');
                }
                return { kind: 'json', data };
            }
            const text = await response.text();
            throw new Error(text || t['compilation_failed'] || 'Compilation failed');
        })
        .then(result => {
            if (savePdf) {
                const data = result.data;
                if (data && data.success && data.pdf_path && Number(data.size || 0) > 0) {
                    setStatus(t['success'], 'status-ok');
                    showNotification(t['save_pdf'], t['compilation_success'], 'success');
                    if (window.cloudIframeCollab && window.cloudIframeCollab.getSession()) window.cloudIframeCollab.notifyLatexCompiled({ pdf_path: data.pdf_path });
                    if (window.parent) {
                        window.parent.postMessage({
                            type: 'PDF_SAVED',
                            path: data.pdf_path,
                            timestamp: Date.now()
                        }, '*');
                    }
                    return { success: true, pdf_path: data.pdf_path, size: data.size };
                }
                setStatus(t['error'], 'status-error');
                showNotification(t['save_pdf'], t['compilation_failed'], 'error');
                throw new Error((data && data.error) || 'PDF was not created');
            }
            displayPdf(result.blob);
            setStatus(t['success'], 'status-ok');
            if (window.cloudIframeCollab && window.cloudIframeCollab.getSession()) window.cloudIframeCollab.notifyLatexCompiled({});
            return { success: true };
        })
        .catch(error => {
            setStatus(t['error'], 'status-error');
            showCompilationError(error.message);
            return { success: false, error: error.message };
        });
        };
        if (!editor) return runCompile();
        const content = editor.getValue();
        if (content !== lastContent) return saveFile().then(runCompile).catch(runCompile);
        return runCompile();
    }
    window.cloudIframeMonacoAiCompile = compileLatex;
    
    function filterLaTeXError(errorText) {
        if (!errorText) return errorText;
        const lines = errorText.split('\n');
        let filteredLines = [];
        let isCapturingError = false;
        let errorBlock = [];
        const skipPatterns = [/^This is pdfTeX,/,/^This is LuaHBTeX/,/^entering extended mode$/,/^LaTeX2e/,/^L3 programming layer/,/^Document Class:/,/^\(.*\\[a-zA-Z]+\./,/^\*geometry\*/,/^\[.*\]$/,/^No file.*\.aux\.$/,/^No file.*\.toc\.$/,/^Transcript written on/,/^Output written on/,/^restricted \\write18 enabled\.$/,/^For additional information/,/^Type  H   for immediate help\.$/,/^See the.*package documentation/,/^Loading MPS to PDF converter/,/^Package.*Info:.*$/,/^LaTeX Warning:/,/^Package.*Warning:/,/^Chapter \d+\.$/,/^\.\.\.$/,/^Running '/,/^Rule '/,/^Latexmk: Log file says/,/^Latexmk: Examining/,/^Latexmk: Getting log file/,/^Latexmk: All targets/,/^Latexmk: Summary of warnings/,/^Latexmk: Errors, so I did not complete/,/^Latexmk: Sometimes/,/^Collected error summary/,/^lualatex: (Command for|Run of rule)/,/^Refer to .*\.log/,/^Missing character:/,/^\s*$/];
        const errorStartPatterns = [/^\! /,/^l\.\d+/,/:\d+:\s/,/^! LaTeX Error:/i,/^LaTeX Error:/i,/Package .+ Error:/i,/^Undefined control sequence/,/^Emergency stop/,/^Missing .* inserted/,/not found/i,/^Runaway argument/,/^Fatal error/i];
        function basenameFromPath(pathLike) {
            const normalized = String(pathLike || '').replace(/\\/g, '/');
            const parts = normalized.split('/').filter(Boolean);
            return parts.length ? parts[parts.length - 1] : '***';
        }
        function cleanPath(line) {
            let cleaned = line;
            cleaned = cleaned.replace(/\(C:\\Program Files\\MiKTeX\\.*?\)/g, '(*)');
            cleaned = cleaned.replace(/C:\\Program Files\\MiKTeX\\[^\s\)]+/g, '***');
            cleaned = cleaned.replace(/C:\\Users\\.*?\\AppData\\Roaming\\MiKTeX\\[^\s\)]+/g, '***');
            cleaned = cleaned.replace(/C:\\Windows\\system32\\config\\systemprofile\\AppData\\[^\s\)]+/g, '***');
            cleaned = cleaned.replace(/[A-Z]:\\[^\s\)\]]+\\[^\s\)\]]+/g, (match) => {
                if (match.match(/\.(tex|aux|toc|log)$/i)) return basenameFromPath(match);
                return '***';
            });
            cleaned = cleaned.replace(/\/(?:var|tmp|home|usr|opt|media|mnt|run|dev\/shm)(?:\/[^\s\)\]:"']*)+/g, (match) => basenameFromPath(match));
            cleaned = cleaned.replace(/\/(?:[^/\s\)\]:"']+\/)+([^/\s\)\]:"']+\.(?:tex|log|aux|sty|cls|pdf|bib|fls|fdb_latexmk))/g, '$1');
            cleaned = cleaned.replace(/\([^)]*\/[^)]*\)/g, (match) => '(' + basenameFromPath(match.slice(1, -1)) + ')');
            cleaned = cleaned.replace(/\([^\)]*\\[^\)]*\)/g, '(*)');
            return cleaned;
        }
        for (let line of lines) {
            line = line.trim();
            if (!line) continue;
            line = cleanPath(line);
            const isErrorStart = errorStartPatterns.some(pattern => pattern.test(line));
            const shouldSkip = skipPatterns.some(pattern => pattern.test(line));
            if (isErrorStart) {
                if (errorBlock.length > 0) {
                    filteredLines.push(...errorBlock);
                    errorBlock = [];
                }
                isCapturingError = true;
                errorBlock.push(line);
            } else if (isCapturingError && !shouldSkip) {
                errorBlock.push(line);
            } else if (!shouldSkip && (line.includes('not found') || line.includes('Error:') || line.includes('error occurred'))) {
                if (errorBlock.length > 0) {
                    filteredLines.push(...errorBlock);
                    errorBlock = [];
                }
                filteredLines.push(line);
                isCapturingError = false;
            } else if (isCapturingError && shouldSkip) {
                if (errorBlock.length > 0) {
                    filteredLines.push(...errorBlock);
                    errorBlock = [];
                }
                isCapturingError = false;
            }
        }
        if (errorBlock.length > 0) filteredLines.push(...errorBlock);
        filteredLines = filteredLines.filter((line, index, self) => {
            return line && line.trim() && self.indexOf(line) === index;
        });
        filteredLines = filteredLines.filter(line => !line.startsWith('...'));
        filteredLines = filteredLines.filter(line => !line.includes('Type  H   for immediate help'));
        filteredLines = filteredLines.filter(line => !line.includes('See the') && !line.includes('package documentation'));
        if (filteredLines.length === 0) {
            const lastLines = lines.slice(-15)
                .filter(line => line.trim())
                .map(cleanPath)
                .filter(line => line && !line.match(/^\s*$/));
            filteredLines = [...new Set(lastLines)];
        }
        const result = filteredLines
            .map(line => line.trim())
            .filter(line => line)
            .join('\n');
        return result || (t['compilation_failed'] || 'Compilation failed');
    }
    
    let lastCompilationErrorText = '';
    function escapeHtmlText(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function copyTextToClipboard(text) {
        const value = String(text ?? '');
        if (!value) return Promise.reject(new Error('empty'));
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }
        return new Promise((resolve, reject) => {
            const ta = document.createElement('textarea');
            ta.value = value;
            ta.setAttribute('readonly', '');
            ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            try {
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('execCommand failed'));
            } catch (err) {
                document.body.removeChild(ta);
                reject(err);
            }
        });
    }
    function showCompilationError(errorText) {
        const filteredError = filterLaTeXError(errorText);
        lastCompilationErrorText = filteredError;
        const pdfView = document.getElementById('pdf-view');
        pdfView.innerHTML = `
            <div class="error-overlay">
                <div class="error-header">
                    <strong>${escapeHtmlText(t['compilation_error'])}</strong>
                    <div class="error-buttons">
                        <button type="button" class="error-btn copy-btn">${escapeHtmlText(t['copy'] || 'Copy')}</button>
                        <button type="button" class="error-btn close-btn">${escapeHtmlText(t['close'] || 'Close')}</button>
                    </div>
                </div>
                <div class="error-content">
                    <pre>${escapeHtmlText(filteredError)}</pre>
                </div>
            </div>
            <div id="pdf-placeholder">${escapeHtmlText(t['pdf_will_appear'])}</div>
        `;
        pdfView.querySelector('.error-btn.copy-btn')?.addEventListener('click', copyErrorText);
        pdfView.querySelector('.error-btn.close-btn')?.addEventListener('click', function() {
            pdfView.querySelector('.error-overlay')?.remove();
        });
    }
    function copyErrorText() {
        const text = lastCompilationErrorText || document.querySelector('.error-content pre')?.textContent || '';
        if (!text) {
            showNotification(t['copy'], t['copy_error'], 'error', 2000);
            return;
        }
        copyTextToClipboard(text).then(() => {
            showNotification(t['copy'], t['copy_success'], 'success', 2000);
        }).catch(() => {
            showNotification(t['copy'], t['copy_error'], 'error', 2000);
        });
    }
    
    function updateFormattingButtonsState() {
        if (!editor) return;
        
        const selection = editor.getSelection();
        const model = editor.getModel();
        const hasSelection = selection && !selection.isEmpty();
        
        // Check if selection contains LaTeX comments
        let hasComment = false;
        if (hasSelection && model) {
            const selectedText = model.getValueInRange(selection);
            hasComment = selectedText.includes('%');
        }
        
        document.querySelectorAll('.formatting-btn').forEach(btn => {
            const command = btn.getAttribute('data-command');
            const alwaysActiveCommands = ['clearFormatting', 'translate', 'comment', 'undo', 'redo'];
            
            if (alwaysActiveCommands.includes(command)) {
                if (command === 'translate') {
                    // The translate button is always available
                    btn.disabled = false;
                    // Highlight if there is a selection
                    if (hasSelection) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                } else if (command === 'comment') {
                    btn.disabled = !hasSelection;
                    // Toggle active class based on whether selection has comments
                    if (hasSelection && hasComment) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                } else {
                    btn.disabled = false;
                }
            } else {
                btn.disabled = !hasSelection;
            }
        });
    }
    
    // Toggle comment function
    function toggleComment() {
        if (!editor) return;
        
        const selection = editor.getSelection();
        if (selection.isEmpty()) return;
        
        const model = editor.getModel();
        const selectedText = model.getValueInRange(selection);
        
        // Check if all lines are commented
        const lines = selectedText.split('\n');
        const allCommented = lines.every(line => line.trim().startsWith('%') || line.trim() === '');
        
        let newText;
        if (allCommented) {
            // Uncomment
            newText = lines.map(line => {
                const trimmed = line.trimStart();
                if (trimmed.startsWith('%')) {
                    return line.replace(/^\s*%/, '');
                }
                return line;
            }).join('\n');
        } else {
            // Comment
            newText = lines.map(line => {
                if (line.trim() !== '') {
                    return '% ' + line;
                }
                return line;
            }).join('\n');
        }
        
        const id = { major: 1, minor: 1 };
        const op = {
            identifier: id,
            range: selection,
            text: newText,
            forceMoveMarkers: true
        };
        editor.executeEdits('comment', [op]);
        editor.focus();
    }
    
    // Delete line function
    function deleteLine() {
        if (!editor) return;
        
        const selection = editor.getSelection();
        const model = editor.getModel();
        
        if (selection.isEmpty()) {
            // Delete current line
            const lineNumber = selection.startLineNumber;
            const lineContent = model.getLineContent(lineNumber);
            const lineRange = new monaco.Range(lineNumber, 1, lineNumber, lineContent.length + 1);
            
            const id = { major: 1, minor: 1 };
            const op = {
                identifier: id,
                range: lineRange,
                text: '',
                forceMoveMarkers: true
            };
            editor.executeEdits('delete-line', [op]);
        } else {
            // Delete selected lines
            const startLine = selection.startLineNumber;
            const endLine = selection.endLineNumber;
            
            const startLineContent = model.getLineContent(startLine);
            const endLineContent = model.getLineContent(endLine);
            
            const range = new monaco.Range(startLine, 1, endLine, endLineContent.length + 1);
            
            const id = { major: 1, minor: 1 };
            const op = {
                identifier: id,
                range: range,
                text: '',
                forceMoveMarkers: true
            };
            editor.executeEdits('delete-lines', [op]);
        }
        
        editor.focus();
    }
    
    function notifyParentSaved() {
      if (window.parent && window.parent !== window) window.parent.postMessage({ type: 'editorSaveResult', ok: true }, '*');
    }

    function saveFile() {
      const content = editor ? editor.getValue() : '';
      return fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'save=true&content=' + encodeURIComponent(content)
      })
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          notifyParentSaved();
          lastContent = content;
          return;
        }
        setStatus(t['save_failed'], 'status-error');
        throw new Error('save failed');
      })
      .catch(error => {
        setStatus(t['save_error'], 'status-error');
        throw error;
      });
    }
    
    // Function to update the Undo/Redo button state
    function updateUndoRedoButtons() {
        if (!editor) return;
        
        const undoBtn = document.querySelector('.formatting-btn[data-command="undo"]');
        const redoBtn = document.querySelector('.formatting-btn[data-command="redo"]');
        
        if (!undoBtn || !redoBtn) return;
        
        // Check whether undo is possible
        const canUndo = editor.getModel()?.canUndo() || false;
        undoBtn.disabled = !canUndo;
        
        // Check whether redo is possible
        const canRedo = editor.getModel()?.canRedo() || false;
        redoBtn.disabled = !canRedo;
        
        // Visual display of inactive buttons
        undoBtn.style.opacity = canUndo ? '1' : '0.5';
        redoBtn.style.opacity = canRedo ? '1' : '0.5';
    }

    // Build one selectable span per visual line instead of one span per PDF text
    // item, so drag-selection is continuous line-by-line rather than a grid of
    // separate boxes.
    function getTextAscent(ctx, fontSize) {
      ctx.font = fontSize + 'px sans-serif';
      const metrics = ctx.measureText('g');
      const ascent = metrics.fontBoundingBoxAscent;
      const descent = metrics.fontBoundingBoxDescent;
      if (ascent && ascent + descent > 0) return ascent / (ascent + descent);
      return 0.8;
    }

    async function renderLineTextLayer(page, viewport, container) {
      const textContent = await page.getTextContent();
      const items = [];
      for (const item of textContent.items) {
        if (!item || typeof item.str !== 'string' || item.str.length === 0) continue;
        const tx = pdfjsLib.Util.transform(viewport.transform, item.transform);
        const fontHeight = Math.hypot(tx[2], tx[3]) || item.height * viewport.scale;
        if (!fontHeight) continue;
        items.push({
          str: item.str,
          x: tx[4],
          baseline: tx[5],
          width: item.width * viewport.scale,
          fontHeight: fontHeight
        });
      }
      if (items.length === 0) return;

      items.sort((a, b) => (a.baseline - b.baseline) || (a.x - b.x));

      const lines = [];
      const baselineTolerance = 1.5;
      for (const item of items) {
        const line = lines[lines.length - 1];
        const maxGap = item.fontHeight * 1.5;
        if (!line || Math.abs(item.baseline - line.baseline) > baselineTolerance || item.x - line.right > maxGap) {
          lines.push({
            baseline: item.baseline,
            fontHeight: item.fontHeight,
            left: item.x,
            right: item.x + item.width,
            items: [item]
          });
        } else {
          line.items.push(item);
          line.left = Math.min(line.left, item.x);
          line.right = Math.max(line.right, item.x + item.width);
          line.fontHeight = Math.max(line.fontHeight, item.fontHeight);
        }
      }

      const measureCtx = document.createElement('canvas').getContext('2d');
      for (const line of lines) {
        let text = '';
        let previousEnd = null;
        for (const item of line.items) {
          if (previousEnd !== null) {
            const gap = item.x - previousEnd;
            if (gap > item.fontHeight * 0.15 && !text.endsWith(' ') && !item.str.startsWith(' ')) text += ' ';
          }
          text += item.str;
          previousEnd = item.x + item.width;
        }
        text = text.replace(/\s+$/, '');
        if (!text.trim()) continue;

        const fontSize = line.fontHeight;
        const span = document.createElement('span');
        span.textContent = text;
        span.style.left = line.left.toFixed(2) + 'px';
        span.style.top = (line.baseline - getTextAscent(measureCtx, fontSize) * fontSize).toFixed(2) + 'px';
        span.style.fontSize = fontSize.toFixed(2) + 'px';
        span.style.fontFamily = 'sans-serif';
        span.style.lineHeight = '1';

        measureCtx.font = fontSize + 'px sans-serif';
        const naturalWidth = measureCtx.measureText(text).width;
        const lineWidth = Math.max(1, line.right - line.left);
        if (naturalWidth > 0) span.style.transform = 'scaleX(' + (lineWidth / naturalWidth) + ')';
        container.appendChild(span);
      }
    }

    let pdfRenderToken = 0;
    let pdfUseFakeWorker = false;
    let pdfWorkerBlobUrl = '';
    let lastPdfBytes = null;
    const pdfViewSettings = { zoom: 'auto', joined: false };
    const PDFJS_WORKER_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
    async function fetchPdfWorkerCode() {
      if (window.__pdfWorkerCode) return window.__pdfWorkerCode;
      const r = await fetch(PDFJS_WORKER_URL, { credentials: 'omit', mode: 'cors' });
      if (!r.ok) throw new Error('pdf worker fetch ' + r.status);
      window.__pdfWorkerCode = await r.text();
      return window.__pdfWorkerCode;
    }
    async function ensurePdfWorkerSrc() {
      if (pdfWorkerBlobUrl) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerBlobUrl;
        return pdfWorkerBlobUrl;
      }
      const code = await fetchPdfWorkerCode();
      pdfWorkerBlobUrl = URL.createObjectURL(new Blob([code], { type: 'text/javascript' }));
      pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerBlobUrl;
      return pdfWorkerBlobUrl;
    }
    async function ensurePdfjsWorkerGlobal() {
      if (window.pdfjsWorker && window.pdfjsWorker.WorkerMessageHandler) return;
      const code = await fetchPdfWorkerCode();
      const wrapped = '(function(){var define=undefined,exports=undefined,module=undefined;\n' + code + '\n})();';
      const url = URL.createObjectURL(new Blob([wrapped], { type: 'text/javascript' }));
      await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = url;
        s.onload = () => { URL.revokeObjectURL(url); resolve(); };
        s.onerror = () => { URL.revokeObjectURL(url); reject(new Error('pdf worker script')); };
        document.head.appendChild(s);
      });
      if (!(window.pdfjsWorker && window.pdfjsWorker.WorkerMessageHandler)) {
        throw new Error('Setting up fake worker failed: WorkerMessageHandler missing');
      }
    }
    async function openPdfDocWithFake(data) {
      await ensurePdfjsWorkerGlobal();
      await ensurePdfWorkerSrc();
      const RealWorker = window.Worker;
      try {
        window.Worker = undefined;
        return await pdfjsLib.getDocument({ data: data.slice(), useSystemFonts: true }).promise;
      } finally {
        window.Worker = RealWorker;
      }
    }
    async function openPdfDoc(data) {
      await ensurePdfWorkerSrc();
      if (pdfUseFakeWorker) return openPdfDocWithFake(data);
      const task = pdfjsLib.getDocument({ data: data.slice(), useSystemFonts: true });
      try {
        return await Promise.race([
          task.promise,
          new Promise((_, reject) => setTimeout(() => reject(new Error('pdf-worker-timeout')), 8000))
        ]);
      } catch (err) {
        try { task.destroy(); } catch (_) {}
        const msg = String(err && err.message || err || '');
        if (!/pdf-worker-timeout|fake worker|WorkerMessageHandler|Failed to fetch|importScripts/i.test(msg)) throw err;
        pdfUseFakeWorker = true;
        return openPdfDocWithFake(data);
      }
    }
    function pdfZoomLabel(z) {
      return z === 'auto' ? (t['pdf_zoom_auto'] || 'Auto') : (Math.round(Number(z) * 100) + '%');
    }
    function ensurePdfContextMenu() {
      let menu = document.getElementById('pdf-context-menu');
      if (menu) return menu;
      menu = document.createElement('div');
      menu.id = 'pdf-context-menu';
      menu.innerHTML = [
        '<div class="pdf-ctx-label">' + (t['pdf_ctx_pages'] || 'Pages') + '</div>',
        '<button type="button" data-gap="split">' + (t['pdf_pages_split'] || 'Separate pages') + '</button>',
        '<button type="button" data-gap="joined">' + (t['pdf_pages_joined'] || 'Continuous pages') + '</button>',
        '<div class="pdf-ctx-label">' + (t['pdf_zoom'] || 'Zoom') + '</div>',
        '<button type="button" data-zoom="auto">' + pdfZoomLabel('auto') + '</button>',
        '<button type="button" data-zoom="0.5">50%</button>',
        '<button type="button" data-zoom="0.75">75%</button>',
        '<button type="button" data-zoom="1">100%</button>',
        '<button type="button" data-zoom="1.25">125%</button>',
        '<button type="button" data-zoom="1.5">150%</button>',
        '<button type="button" data-zoom="2">200%</button>'
      ].join('');
      document.body.appendChild(menu);
      menu.addEventListener('click', ev => {
        const btn = ev.target.closest('button');
        if (!btn) return;
        if (btn.dataset.gap) setPdfGapMode(btn.dataset.gap === 'joined');
        if (btn.dataset.zoom) setPdfZoom(btn.dataset.zoom === 'auto' ? 'auto' : Number(btn.dataset.zoom));
        hidePdfContextMenu();
      });
      document.addEventListener('click', ev => {
        if (!menu.classList.contains('show')) return;
        if (!menu.contains(ev.target)) hidePdfContextMenu();
      });
      document.addEventListener('keydown', ev => { if (ev.key === 'Escape') hidePdfContextMenu(); });
      return menu;
    }
    function syncPdfContextMenu() {
      const menu = ensurePdfContextMenu();
      menu.querySelectorAll('button[data-gap]').forEach(b => b.classList.toggle('active', (b.dataset.gap === 'joined') === pdfViewSettings.joined));
      menu.querySelectorAll('button[data-zoom]').forEach(b => {
        const val = b.dataset.zoom === 'auto' ? 'auto' : Number(b.dataset.zoom);
        b.classList.toggle('active', val === pdfViewSettings.zoom || String(val) === String(pdfViewSettings.zoom));
      });
    }
    function showPdfContextMenu(x, y) {
      const menu = ensurePdfContextMenu();
      syncPdfContextMenu();
      menu.classList.add('show');
      const pad = 8;
      const w = menu.offsetWidth || 180;
      const h = menu.offsetHeight || 260;
      menu.style.left = Math.max(pad, Math.min(window.innerWidth - w - pad, x)) + 'px';
      menu.style.top = Math.max(pad, Math.min(window.innerHeight - h - pad, y)) + 'px';
    }
    function hidePdfContextMenu() {
      const menu = document.getElementById('pdf-context-menu');
      if (menu) menu.classList.remove('show');
    }
    function updatePdfGapUi(root) {
      const stage = (root || document).querySelector?.('.pdf-stage') || document.querySelector('.pdf-stage');
      if (!stage) return;
      stage.classList.toggle('pdf-pages-joined', pdfViewSettings.joined);
      stage.querySelectorAll('.pdf-page-gap').forEach(gap => {
        gap.title = pdfViewSettings.joined ? (t['pdf_gap_join_hint'] || '') : (t['pdf_gap_split_hint'] || '');
        gap.setAttribute('aria-label', gap.title);
      });
    }
    function setPdfGapMode(joined) {
      pdfViewSettings.joined = !!joined;
      updatePdfGapUi();
      syncPdfContextMenu();
    }
    function setPdfZoom(zoom) {
      pdfViewSettings.zoom = zoom === 'auto' ? 'auto' : Number(zoom);
      syncPdfContextMenu();
      if (lastPdfBytes) displayPdf(new Blob([lastPdfBytes], { type: 'application/pdf' }), { forceScale: true });
    }
    function makePdfPageGap() {
      const separator = document.createElement('div');
      separator.className = 'pdf-page-gap';
      separator.tabIndex = 0;
      separator.title = pdfViewSettings.joined ? (t['pdf_gap_join_hint'] || '') : (t['pdf_gap_split_hint'] || '');
      separator.addEventListener('click', ev => {
        ev.preventDefault();
        ev.stopPropagation();
        setPdfGapMode(!pdfViewSettings.joined);
      });
      separator.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          setPdfGapMode(!pdfViewSettings.joined);
        }
      });
      return separator;
    }
    function bindPdfViewChrome(pdfView) {
      if (pdfView.__pdfChromeBound) return;
      pdfView.__pdfChromeBound = true;
      pdfView.addEventListener('contextmenu', ev => {
        if (!pdfView.querySelector('.pdf-page-wrapper')) return;
        ev.preventDefault();
        showPdfContextMenu(ev.clientX, ev.clientY);
      });
    }
    function resolvePdfRenderScale(pageWidth, pdfView, forceScale) {
      const availableWidth = Math.max(200, (pdfView.clientWidth || 600) - 30);
      const fitScale = Math.min(2, Math.max(0.5, availableWidth / Math.max(1, pageWidth)));
      if (pdfViewSettings.zoom === 'auto') {
        if (!forceScale) {
          const prev = pdfView.querySelector('.pdf-page-wrapper');
          const keep = prev ? parseFloat(prev.dataset.scale) : NaN;
          if (Number.isFinite(keep) && keep > 0) return keep;
        }
        return fitScale;
      }
      return Number(pdfViewSettings.zoom) || fitScale;
    }
    function displayPdf(pdfBlob, opts = {}) {
      const pdfView = document.getElementById('pdf-view');
      const placeholder = document.getElementById('pdf-placeholder');
      if (placeholder) placeholder.style.display = 'none';
      bindPdfViewChrome(pdfView);
      const token = ++pdfRenderToken;
      const forceScale = !!opts.forceScale;
      const prevWrapper = pdfView.querySelector('.pdf-page-wrapper');
      const savedScroll = {
        top: pdfView.scrollTop,
        left: pdfView.scrollLeft
      };
      const replacing = !!prevWrapper;
      if (!replacing) {
        pdfView.innerHTML = '<div class="pdf-loading">' + t['pdf_loading'] + '</div>';
      } else {
        pdfView.classList.add('pdf-updating');
      }
      pdfBlob.arrayBuffer().then(async buffer => {
        if (token !== pdfRenderToken) return;
        const data = new Uint8Array(buffer);
        lastPdfBytes = data.slice();
        const pdf = await openPdfDoc(data);
        if (token !== pdfRenderToken) { try { pdf.destroy(); } catch (_) {} return; }
        const firstPage = await pdf.getPage(1);
        const baseViewport = firstPage.getViewport({ scale: 1 });
        const renderScale = resolvePdfRenderScale(baseViewport.width, pdfView, forceScale || !replacing);
        const pageRenderPromises = [];
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
          pageRenderPromises.push(
            pdf.getPage(pageNum).then(async page => {
              const viewport = page.getViewport({ scale: renderScale });
              const wrapper = document.createElement('div');
              wrapper.className = 'pdf-page-wrapper';
              wrapper.dataset.scale = String(renderScale);
              wrapper.dataset.zoomMode = String(pdfViewSettings.zoom);
              wrapper.style.width = viewport.width + 'px';
              wrapper.style.height = viewport.height + 'px';
              const canvas = document.createElement('canvas');
              const outputScale = Math.max(window.devicePixelRatio || 1, 2);
              canvas.width = Math.floor(viewport.width * outputScale);
              canvas.height = Math.floor(viewport.height * outputScale);
              canvas.style.width = viewport.width + 'px';
              canvas.style.height = viewport.height + 'px';
              canvas.className = 'pdf-page';
              canvas.setAttribute('data-page-number', pageNum);
              wrapper.appendChild(canvas);
              const context = canvas.getContext('2d');
              context.scale(outputScale, outputScale);
              await page.render({ canvasContext: context, viewport: viewport }).promise;
              const textLayer = document.createElement('div');
              textLayer.className = 'text-layer';
              wrapper.appendChild(textLayer);
              await renderLineTextLayer(page, viewport, textLayer);
              return { wrapper, pageNum };
            })
          );
        }
        const renderedPages = await Promise.all(pageRenderPromises);
        if (token !== pdfRenderToken) return;
        renderedPages.sort((a, b) => a.pageNum - b.pageNum);
        const frag = document.createDocumentFragment();
        const stage = document.createElement('div');
        stage.className = 'pdf-stage' + (pdfViewSettings.joined ? ' pdf-pages-joined' : '');
        renderedPages.forEach((item, index) => {
          stage.appendChild(item.wrapper);
          if (index < renderedPages.length - 1) stage.appendChild(makePdfPageGap());
        });
        frag.appendChild(stage);
        const oldScrollHeight = pdfView.scrollHeight;
        pdfView.replaceChildren(frag);
        updatePdfGapUi(pdfView);
        pdfView.classList.remove('pdf-updating');
        if (replacing) {
          if (oldScrollHeight > 0 && pdfView.scrollHeight > 0) {
            pdfView.scrollTop = Math.min(Math.max(0, pdfView.scrollHeight - pdfView.clientHeight), savedScroll.top * (pdfView.scrollHeight / oldScrollHeight));
          } else {
            pdfView.scrollTop = savedScroll.top;
          }
          pdfView.scrollLeft = savedScroll.left;
        }
        setStatus(t['pdf_loaded'], 'status-ok');
      }).catch(error => {
        if (token !== pdfRenderToken) return;
        pdfView.classList.remove('pdf-updating');
        if (!replacing) {
          pdfView.innerHTML = '<div style="color: red;">' + t['pdf_load_error'] + ' ' + error.message + '</div>';
        }
        setStatus(t['status_err'], 'status-error');
      });
    }

    function loadTemplate(templateKey) {
      if (!templateKey) return;
      fetch('<?= htmlspecialchars($templatesUrl) ?>')
        .then(response => response.json())
        .then(data => {
          const template = data.templates[templateKey];
          if (template) {
            const templateContent = template.content[currentLang] || template.content['en'];
            if (templateContent) showTemplateConfirm(template, templateContent, templateKey);
          }
        })
        .catch(error => {
          setStatus(t['template_error'], 'status-error');
        });
    }
    
    function showTemplateConfirm(template, templateContent, templateKey) {
      const modal = document.getElementById('template-modal');
      const modalBody = document.getElementById('template-modal-body');
      const templateName = template.names[currentLang] || template.names['en'];
      modalBody.innerHTML = `
        <p>${t['load_template_confirm'].replace('{0}', templateName)}</p>
        <div class="modal-footer">
          <button class="btn" id="template-cancel">${t['cancel']}</button>
          <button class="btn primary" id="template-confirm">${t['insert']}</button>
        </div>
      `;
      modal.style.display = 'flex';
      document.getElementById('template-confirm').addEventListener('click', function() {
        if (editor) editor.setValue(templateContent);
        setStatus(t['template_loaded'], 'status-ok');
        modal.style.display = 'none';
      });
      document.getElementById('template-cancel').addEventListener('click', function() {
        modal.style.display = 'none';
      });
    }
    
    const elementTemplates = {
  part: '\\part{Part Title}',
  chapter: '\\chapter{Chapter Title}',
  section: '\\section{Section Title}',
  subsection: '\\subsection{Subsection Title}',
  subsubsection: '\\subsubsection{Subsubsection Title}',
  figure: '\\begin{figure}[h]\n  \\centering\n  \\includegraphics[width=0.5\\textwidth]{example-image}\n  \\caption{Figure caption}\n  \\label{fig:example}\n\\end{figure}',
  table: '% Table will be generated by modal',
  itemize: '\\begin{itemize}\n  \\item First item\n  \\item Second item\n  \\item Third item\n\\end{itemize}',
  enumerate: '\\begin{enumerate}\n  \\item First item\n  \\item Second item\n  \\item Third item\n\\end{enumerate}',
  verbatim: '\\begin{verbatim}\nCode or text here\n\\end{verbatim}',
  equation: '\\begin{equation}\n  E = mc^2\n\\end{equation}',
  align: '\\begin{align}\n  a &= b + c \\\\\n  d &= e + f\n\\end{align}',
  frac: '$\\frac{a}{b}$',
  sqrt: '$\\sqrt{x}$',
  matrix: '$\\begin{matrix}\n  a & b \\\\\n  c & d\n\\end{matrix}$',
  pmatrix: '$\\begin{pmatrix}\n  a & b \\\\\n  c & d\n\\end{pmatrix}$',
  sum: '$\\sum_{i=1}^{n} i$',
  int: '$\\int_{a}^{b} f(x) \\, dx$',
  textbf: '\\textbf{bold text}',
  textit: '\\textit{italic text}',
  label: '\\label{sec:introduction}',
  ref: '\\ref{sec:introduction}',
  symbols: '% Symbols will be inserted from modal',
  insert_pdf: '% PDF inclusion code will be generated by modal',
  listing: '% Code listing will be generated by modal'
};
    
    const symbolCategories = {
      greek: [
        { code: '$\\alpha$', symbol: 'α' }, { code: '$\\beta$', symbol: 'β' }, { code: '$\\gamma$', symbol: 'γ' },
        { code: '$\\delta$', symbol: 'δ' }, { code: '$\\epsilon$', symbol: 'ε' }, { code: '$\\zeta$', symbol: 'ζ' },
        { code: '$\\eta$', symbol: 'η' }, { code: '$\\theta$', symbol: 'θ' }, { code: '$\\iota$', symbol: 'ι' },
        { code: '$\\kappa$', symbol: 'κ' }, { code: '$\\lambda$', symbol: 'λ' }, { code: '$\\mu$', symbol: 'μ' },
        { code: '$\\nu$', symbol: 'ν' }, { code: '$\\xi$', symbol: 'ξ' }, { code: '$\\pi$', symbol: 'π' },
        { code: '$\\rho$', symbol: 'ρ' }, { code: '$\\sigma$', symbol: 'σ' }, { code: '$\\tau$', symbol: 'τ' },
        { code: '$\\upsilon$', symbol: 'υ' }, { code: '$\\phi$', symbol: 'φ' }, { code: '$\\chi$', symbol: 'χ' },
        { code: '$\\psi$', symbol: 'ψ' }, { code: '$\\omega$', symbol: 'ω' }, { code: '$\\Gamma$', symbol: 'Γ' },
        { code: '$\\Delta$', symbol: 'Δ' }, { code: '$\\Theta$', symbol: 'Θ' }, { code: '$\\Lambda$', symbol: 'Λ' },
        { code: '$\\Xi$', symbol: 'Ξ' }, { code: '$\\Pi$', symbol: 'Π' }, { code: '$\\Sigma$', symbol: 'Σ' },
        { code: '$\\Upsilon$', symbol: 'Υ' }, { code: '$\\Phi$', symbol: 'Φ' }, { code: '$\\Psi$', symbol: 'Ψ' },
        { code: '$\\Omega$', symbol: 'Ω' }
      ],
      math: [
        { code: '$\\pm$', symbol: '±' }, { code: '$\\mp$', symbol: '∓' }, { code: '$\\times$', symbol: '×' },
        { code: '$\\div$', symbol: '÷' }, { code: '$\\cdot$', symbol: '·' }, { code: '$\\ast$', symbol: '∗' },
        { code: '$\\circ$', symbol: '∘' }, { code: '$\\bullet$', symbol: '•' }, { code: '$\\oplus$', symbol: '⊕' },
        { code: '$\\ominus$', symbol: '⊖' }, { code: '$\\otimes$', symbol: '⊗' }, { code: '$\\oslash$', symbol: '⊘' },
        { code: '$\\odot$', symbol: '⊙' }, { code: '$\\dagger$', symbol: '†' }, { code: '$\\ddagger$', symbol: '‡' },
        { code: '$\\amalg$', symbol: '⨿' }, { code: '$\\cap$', symbol: '∩' }, { code: '$\\cup$', symbol: '∪' },
        { code: '$\\uplus$', symbol: '⊎' }, { code: '$\\sqcap$', symbol: '⊓' }, { code: '$\\sqcup$', symbol: '⊔' },
        { code: '$\\vee$', symbol: '∨' }, { code: '$\\wedge$', symbol: '∧' }, { code: '$\\setminus$', symbol: '∖' },
        { code: '$\\wr$', symbol: '≀' }, { code: '$\\diamond$', symbol: '⋄' }, { code: '$\\bigtriangleup$', symbol: '△' },
        { code: '$\\bigtriangledown$', symbol: '▽' }, { code: '$\\triangleleft$', symbol: '◁' }, { code: '$\\triangleright$', symbol: '▷' }
      ],
      arrows: [
        { code: '$\\leftarrow$', symbol: '←' }, { code: '$\\rightarrow$', symbol: '→' }, { code: '$\\leftrightarrow$', symbol: '↔' },
        { code: '$\\Leftarrow$', symbol: '⇐' }, { code: '$\\Rightarrow$', symbol: '⇒' }, { code: '$\\Leftrightarrow$', symbol: '⇔' },
        { code: '$\\uparrow$', symbol: '↑' }, { code: '$\\downarrow$', symbol: '↓' }, { code: '$\\updownarrow$', symbol: '↕' },
        { code: '$\\Uparrow$', symbol: '⇑' }, { code: '$\\Downarrow$', symbol: '⇓' }, { code: '$\\Updownarrow$', symbol: '⇕' },
        { code: '$\\mapsto$', symbol: '↦' }, { code: '$\\hookleftarrow$', symbol: '↩' }, { code: '$\\hookrightarrow$', symbol: '↪' },
        { code: '$\\leftharpoonup$', symbol: '↼' }, { code: '$\\rightharpoonup$', symbol: '⇀' }, { code: '$\\leftharpoondown$', symbol: '↽' },
        { code: '$\\rightharpoondown$', symbol: '⇁' }, { code: '$\\rightleftharpoons$', symbol: '⇌' }, { code: '$\\longleftarrow$', symbol: '⟵' },
        { code: '$\\longrightarrow$', symbol: '⟶' }, { code: '$\\longleftrightarrow$', symbol: '⟷' }, { code: '$\\Longleftarrow$', symbol: '⟸' },
        { code: '$\\Longrightarrow$', symbol: '⟹' }, { code: '$\\Longleftrightarrow$', symbol: '⟺' }
      ],
      relations: [
        { code: '$\\leq$', symbol: '≤' }, { code: '$\\geq$', symbol: '≥' }, { code: '$\\equiv$', symbol: '≡' },
        { code: '$\\sim$', symbol: '∼' }, { code: '$\\simeq$', symbol: '≃' }, { code: '$\\approx$', symbol: '≈' },
        { code: '$\\cong$', symbol: '≅' }, { code: '$\\propto$', symbol: '∝' }, { code: '$\\neq$', symbol: '≠' },
        { code: '$\\in$', symbol: '∈' }, { code: '$\\notin$', symbol: '∉' }, { code: '$\\subset$', symbol: '⊂' },
        { code: '$\\supset$', symbol: '⊃' }, { code: '$\\subseteq$', symbol: '⊆' }, { code: '$\\supseteq$', symbol: '⊇' },
        { code: '$\\sqsubseteq$', symbol: '⊑' }, { code: '$\\sqsupseteq$', symbol: '⊒' }, { code: '$\\prec$', symbol: '≺' },
        { code: '$\\succ$', symbol: '≻' }, { code: '$\\preceq$', symbol: '≼' }, { code: '$\\succeq$', symbol: '≽' },
        { code: '$\\parallel$', symbol: '∥' }, { code: '$\\perp$', symbol: '⟂' }, { code: '$\\mid$', symbol: '∣' },
        { code: '$\\smile$', symbol: '⌣' }, { code: '$\\frown$', symbol: '⌢' }, { code: '$\\asymp$', symbol: '≍' },
        { code: '$\\bowtie$', symbol: '⋈' }, { code: '$\\doteq$', symbol: '≐' }, { code: '$\\models$', symbol: '⊧' }
      ],
      other: [
        { code: '$\\infty$', symbol: '∞' }, { code: '$\\partial$', symbol: '∂' }, { code: '$\\nabla$', symbol: '∇' },
        { code: '$\\forall$', symbol: '∀' }, { code: '$\\exists$', symbol: '∃' }, { code: '$\\nexists$', symbol: '∄' },
        { code: '$\\emptyset$', symbol: '∅' }, { code: '$\\varnothing$', symbol: '∅' }, { code: '$\\hbar$', symbol: 'ℏ' },
        { code: '$\\ell$', symbol: 'ℓ' }, { code: '$\\Re$', symbol: 'ℜ' }, { code: '$\\Im$', symbol: 'ℑ' },
        { code: '$\\aleph$', symbol: 'ℵ' }, { code: '$\\wp$', symbol: '℘' }, { code: '$\\dagger$', symbol: '†' },
        { code: '$\\ddagger$', symbol: '‡' }, { code: '$\\S$', symbol: '§' }, { code: '$\\P$', symbol: '¶' },
        { code: '$\\copyright$', symbol: '©' }, { code: '$\\pounds$', symbol: '£' }, { code: '$\\yen$', symbol: '¥' },
        { code: '$\\euro$', symbol: '€' }, { code: '$\\degree$', symbol: '°' }, { code: '$\\angle$', symbol: '∠' },
        { code: '$\\measuredangle$', symbol: '∡' }, { code: '$\\sphericalangle$', symbol: '∢' }, { code: '$\\top$', symbol: '⊤' },
        { code: '$\\bot$', symbol: '⊥' }, { code: '$\\clubsuit$', symbol: '♣' }, { code: '$\\diamondsuit$', symbol: '♦' },
        { code: '$\\heartsuit$', symbol: '♥' }, { code: '$\\spadesuit$', symbol: '♠' }
      ]
    };
    
    function initSymbolsModal() {
      const greekGrid = document.getElementById('greek-symbols');
      symbolCategories.greek.forEach(symbol => {
        const btn = document.createElement('button');
        btn.className = 'symbol-btn';
        btn.innerHTML = `${symbol.symbol}<br><small>${symbol.code}</small>`;
        btn.title = symbol.code;
        btn.addEventListener('click', () => insertSymbol(symbol.code));
        greekGrid.appendChild(btn);
      });
      const mathGrid = document.getElementById('math-symbols');
      symbolCategories.math.forEach(symbol => {
        const btn = document.createElement('button');
        btn.className = 'symbol-btn';
        btn.innerHTML = `${symbol.symbol}<br><small>${symbol.code}</small>`;
        btn.title = symbol.code;
        btn.addEventListener('click', () => insertSymbol(symbol.code));
        mathGrid.appendChild(btn);
      });
      const arrowGrid = document.getElementById('arrow-symbols');
      symbolCategories.arrows.forEach(symbol => {
        const btn = document.createElement('button');
        btn.className = 'symbol-btn';
        btn.innerHTML = `${symbol.symbol}<br><small>${symbol.code}</small>`;
        btn.title = symbol.code;
        btn.addEventListener('click', () => insertSymbol(symbol.code));
        arrowGrid.appendChild(btn);
      });
      const relationGrid = document.getElementById('relation-symbols');
      symbolCategories.relations.forEach(symbol => {
        const btn = document.createElement('button');
        btn.className = 'symbol-btn';
        btn.innerHTML = `${symbol.symbol}<br><small>${symbol.code}</small>`;
        btn.title = symbol.code;
        btn.addEventListener('click', () => insertSymbol(symbol.code));
        relationGrid.appendChild(btn);
      });
      const otherGrid = document.getElementById('other-symbols');
      symbolCategories.other.forEach(symbol => {
        const btn = document.createElement('button');
        btn.className = 'symbol-btn';
        btn.innerHTML = `${symbol.symbol}<br><small>${symbol.code}</small>`;
        btn.title = symbol.code;
        btn.addEventListener('click', () => insertSymbol(symbol.code));
        otherGrid.appendChild(btn);
      });
    }
    
    function insertSymbol(symbolCode) {
      if (editor) {
        const selection = editor.getSelection();
        const id = { major: 1, minor: 1 };
        const op = {
          identifier: id,
          range: selection,
          text: symbolCode,
          forceMoveMarkers: true
        };
        editor.executeEdits('insert-symbol', [op]);
        editor.focus();
      }
      closeModal('symbols-modal');
    }
    
    function showPdfModal() {
      document.getElementById('pdf-modal').style.display = 'flex';
    }
    
    function insertPdfCode(withToc = true) {
          ensurePdfPagesPackage();
          const pages = document.getElementById('pdf-pages').value || '-';
          const scale = document.getElementById('pdf-scale').value || '1.0';
          const orientation = document.getElementById('pdf-orientation').value;
          const addToc = withToc && document.getElementById('pdf-add-toc').checked;
          const tocLevel = document.getElementById('pdf-toc-level').value;
          const tocTitle = document.getElementById('pdf-toc-title').value;
          let options = `pages={${pages}},scale=${scale}`;
          if (orientation === 'landscape') options += ',landscape=true';
          const pdfCode = `\\includepdf[${options}]{filename.pdf}`;
          let finalCode = pdfCode;
          if (addToc && tocTitle) {
            const tocCommand = `\\addcontentsline{toc}{${tocLevel}}{\\numberline{${getNextNumber(tocLevel)}}${tocTitle}}`;
            finalCode = tocCommand + '\n' + pdfCode;
          }
          if (editor) {
            const selection = editor.getSelection();
            const id = { major: 1, minor: 1 };
            const op = {
              identifier: id,
              range: selection,
              text: finalCode,
              forceMoveMarkers: true
            };
            editor.executeEdits('insert-pdf', [op]);
            editor.focus();
          }
          closeModal('pdf-modal');
        }

        function ensurePdfPagesPackage() {
        if (!editor) return;
        const model = editor.getModel();
        const content = model.getValue();
        
        const hasPdfPagesPackage = /\\usepackage\s*(?:\[[^\]]*\])?\s*\{pdfpages\}/i.test(content);
        
        if (hasPdfPagesPackage) {
            return true;
        }
        
        let insertPos = 0;
        let insertLine = 0;
        
        const docClassRegex = /\\documentclass(?:\[[^\]]*\])?\s*\{[^}]*\}/i;
        const docClassMatch = docClassRegex.exec(content);
        
        if (docClassMatch) {
            const beginDocumentIndex = content.indexOf('\\begin{document}');
            
            if (beginDocumentIndex !== -1) {
                const preamble = content.substring(0, beginDocumentIndex);
                const usePackageMatches = preamble.match(/\\usepackage\s*(?:\[[^\]]*\])?\s*\{[^}]*\}/gi);
                
                if (usePackageMatches && usePackageMatches.length > 0) {
                    const lastUsePackage = usePackageMatches[usePackageMatches.length - 1];
                    const lastIndex = preamble.lastIndexOf(lastUsePackage);
                    insertPos = lastIndex + lastUsePackage.length;
                    
                    const textBeforeInsert = preamble.substring(0, insertPos);
                    insertLine = (textBeforeInsert.match(/\n/g) || []).length;
                } else {
                    insertPos = docClassMatch.index + docClassMatch[0].length;
                    const textBeforeInsert = content.substring(0, insertPos);
                    insertLine = (textBeforeInsert.match(/\n/g) || []).length;
                }
                
                const range = new monaco.Range(insertLine + 1, 1, insertLine + 1, 1);
                const id = { major: 1, minor: 1 };
                const op = {
                  identifier: id,
                  range: range,
                  text: '\\usepackage{pdfpages}\n',
                  forceMoveMarkers: true
                };
                editor.executeEdits('add-pdfpages-package', [op]);
                return true;
            }
        }
        
        const range = new monaco.Range(1, 1, 1, 1);
        const id = { major: 1, minor: 1 };
        const op = {
          identifier: id,
          range: range,
          text: '\\usepackage{pdfpages}\n',
          forceMoveMarkers: true
        };
        editor.executeEdits('add-pdfpages-package', [op]);
        return true;
    }

    function getNextNumber(level) {
      return '';
    }
    
    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }
    
    function setupModalEvents() {
      document.querySelectorAll('.modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
          this.closest('.modal').style.display = 'none';
        });
      });
      document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
          if (e.target === this) this.style.display = 'none';
        });
      });
      document.getElementById('notification-ok').addEventListener('click', function() {
        document.getElementById('notification-modal').style.display = 'none';
      });
      document.getElementById('table-cancel').addEventListener('click', () => closeModal('table-modal'));
      document.getElementById('pdf-insert').addEventListener('click', () => insertPdfCode(true));
      document.getElementById('pdf-insert-simple').addEventListener('click', () => insertPdfCode(false));
      document.getElementById('pdf-cancel').addEventListener('click', () => closeModal('pdf-modal'));
      document.getElementById('symbols-cancel').addEventListener('click', () => closeModal('symbols-modal'));
      document.getElementById('pdf-add-toc').addEventListener('change', function() {
        document.getElementById('pdf-toc-settings').style.display = this.checked ? 'block' : 'none';
      });
      
      // Translation modal events
      document.getElementById('translate-btn-toolbar').addEventListener('click', openTranslationModal);
      document.getElementById('translate-btn').addEventListener('click', performTranslation);
      document.getElementById('translate-again-btn').addEventListener('click', performTranslation);
      document.getElementById('replace-text-btn').addEventListener('click', replaceWithTranslation);

      // Listing modal events
      document.getElementById('listing-cancel').addEventListener('click', () => closeModal('listing-modal'));
      document.getElementById('listing-insert').addEventListener('click', insertListing);
      document.getElementById('listing-copy-code').addEventListener('click', copyListingCode);
      
      // Save translation parameters when the modal closes
      document.getElementById('translation-modal').addEventListener('close', function() {
        lastTranslationParams.sourceLang = document.getElementById('source-lang').value;
        lastTranslationParams.targetLang = document.getElementById('target-lang').value;
      });
    }
    
    function openTranslationModal() {
      const selection = editor ? editor.getSelection() : null;
      let selectedText = '';
      
      // If there is a selection, take it
      if (selection && !selection.isEmpty()) {
        selectedText = editor.getModel().getValueInRange(selection);
        currentSelectedText = selectedText;
        document.getElementById('source-text').value = selectedText;
      } else {
        // If there is no selection, clear the fields
        currentSelectedText = '';
        document.getElementById('source-text').value = '';
        document.getElementById('target-text').value = '';
      }
      
      // If there is a saved translation for this text, show it
      if (lastTranslationResult && lastTranslationParams.sourceLang === document.getElementById('source-lang').value && 
          lastTranslationParams.targetLang === document.getElementById('target-lang').value &&
          selectedText === currentSelectedText) {
        document.getElementById('target-text').value = lastTranslationResult;
      } else {
        document.getElementById('target-text').value = '';
      }
      
      document.getElementById('translation-modal').style.display = 'flex';
    }
    
    function performTranslation() {
      const sourceText = document.getElementById('source-text').value;
      const sourceLang = document.getElementById('source-lang').value;
      const targetLang = document.getElementById('target-lang').value;
      
      if (!sourceText.trim()) {
        return;
      }
      
      const translateBtn = document.getElementById('translate-btn');
      const originalText = translateBtn.innerHTML;
      translateBtn.innerHTML = '<span class="translation-loading"></span> ' + t['translating'];
      translateBtn.disabled = true;
      
      const formData = new FormData();
      formData.append('translate', 'true');
      formData.append('text', sourceText);
      formData.append('source_lang', sourceLang);
      formData.append('target_lang', targetLang);
      
      // Keep the promise to track status
      lastTranslationPromise = fetch('', { method: 'POST', body: formData, timeout: 180000 })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('target-text').value = data.translated_text;
          lastTranslationResult = data.translated_text;
          lastTranslationParams = { sourceLang, targetLang };
          
          // If the modal is closed, show a notification in the status bar
          if (document.getElementById('translation-modal').style.display !== 'flex') {
            setStatus(t['translation_background'] + ': ' + t['translation_completed'], 'status-ok');
          }
        } else {
          if (document.getElementById('translation-modal').style.display !== 'flex') {
            setStatus(t['translation_failed'], 'status-error');
          }
        }
        return data;
      })
      .catch(error => {
        if (document.getElementById('translation-modal').style.display !== 'flex') {
          setStatus(t['translation_failed'], 'status-error');
        }
        throw error;
      })
      .finally(() => {
        translateBtn.innerHTML = originalText;
        translateBtn.disabled = false;
      });
    }
    
    function replaceWithTranslation() {
      const translatedText = document.getElementById('target-text').value;
      if (!translatedText) {
        return;
      }
      
      if (editor) {
        const selection = editor.getSelection();
        if (!selection.isEmpty()) {
          const id = { major: 1, minor: 1 };
          const op = {
            identifier: id,
            range: selection,
            text: translatedText,
            forceMoveMarkers: true
          };
          editor.executeEdits('replace-translation', [op]);
        }
      }
      
      closeModal('translation-modal');
    }

    function insertElement(elementType) {
      if (elementType === 'symbols') {
        document.getElementById('symbols-modal').style.display = 'flex';
        return;
      }
      if (elementType === 'table') {
        document.getElementById('table-modal').style.display = 'flex';
        return;
      }
      if (elementType === 'insert_pdf') {
        showPdfModal();
        return;
      }
      if (elementType === 'listing') {
        openListingModal();
        return;
      }
      const template = elementTemplates[elementType];
      if (!template) return;
      if (editor) {
        const selection = editor.getSelection();
        const id = { major: 1, minor: 1 };
        const op = {
          identifier: id,
          range: selection,
          text: template,
          forceMoveMarkers: true
        };
        editor.executeEdits('insert-element', [op]);
        editor.focus();
      }
    }
    
    
    function initDropdowns() {
    const templateBtn = document.getElementById('template-btn');
    const templateDropdown = document.getElementById('template-dropdown');
    
    templateBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        
        // Close other dropdowns
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            if (dropdown !== templateDropdown) {
                dropdown.classList.remove('show');
            }
        });
        
        templateDropdown.classList.toggle('show');
    });
    
    const insertBtn = document.getElementById('insert-btn');
    const insertDropdown = document.getElementById('insert-dropdown');
    
    insertBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        e.preventDefault();
        
        // Close other dropdowns
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            if (dropdown !== insertDropdown) {
                dropdown.classList.remove('show');
            }
        });
        
        insertDropdown.classList.toggle('show');
    });
    
    document.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const value = this.getAttribute('data-value');
            if (this.closest('#template-dropdown')) loadTemplate(value);
            else if (this.closest('#insert-dropdown')) insertElement(value);
            
            // Close the dropdown after selection
            this.closest('.dropdown-content').classList.remove('show');
        });
    });
    
    // Close the dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                dropdown.classList.remove('show');
            });
        }
    });
    
    // Prevent closing on click inside the dropdown
    document.querySelectorAll('.dropdown-content').forEach(dropdown => {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
}
    
    function initFormattingToolbar() {
        updateFormattingButtonsState();
        
        document.querySelectorAll('.formatting-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const command = this.getAttribute('data-command');
            if (command === 'translate') {
                openTranslationModal();
            } else if (command === 'comment') {
                toggleComment();
            } else if (command === 'undo') {
                if (editor && editor.getModel()?.canUndo()) {
                    editor.trigger('undo', 'undo');
                    // Update the button state after execution
                    setTimeout(updateUndoRedoButtons, 50);
                }
            } else if (command === 'redo') {
                if (editor && editor.getModel()?.canRedo()) {
                    editor.trigger('redo', 'redo');
                    // Update the button state after execution
                    setTimeout(updateUndoRedoButtons, 50);
                }
            } else {
                applyFormatting(command);
            }
        });
    });
    
    if (editor) {
      editor.onDidChangeCursorSelection(updateFormattingButtonsState);
      editor.onDidChangeModelContent(updateFormattingButtonsState);
    }
}
    
    function applyFormatting(command) {
      const btn = document.querySelector(`.formatting-btn[data-command="${command}"]`);
    if (btn && btn.disabled) return;
    
    if (!editor) return;
    
    const selection = editor.getSelection();
    if (selection.isEmpty()) return;
    
    const selectedText = editor.getModel().getValueInRange(selection);
      let formattedText = '';
      switch(command) {
        case 'bold':
          formattedText = `\\textbf{${selectedText}}`;
          break;
        case 'italic':
          formattedText = `\\textit{${selectedText}}`;
          break;
        case 'underline':
          formattedText = `\\underline{${selectedText}}`;
          break;
        case 'strikethrough':
          formattedText = `\\sout{${selectedText}}`;
          break;
        case 'alignLeft':
          formattedText = `\\begin{flushleft}\n${selectedText}\n\\end{flushleft}`;
          break;
        case 'alignCenter':
          formattedText = `\\begin{center}\n${selectedText}\n\\end{center}`;
          break;
        case 'alignRight':
          formattedText = `\\begin{flushright}\n${selectedText}\n\\end{flushright}`;
          break;
        case 'alignJustify':
          formattedText = selectedText;
          break;
        case 'superscript':
          formattedText = `\\textsuperscript{${selectedText}}`;
          break;
        case 'subscript':
          formattedText = `\\textsubscript{${selectedText}}`;
          break;
        case 'code':
          formattedText = `\\begin{verbatim}\n${selectedText}\n\\end{verbatim}`;
          break;
        case 'quote':
          formattedText = `\\begin{quote}\n${selectedText}\n\\end{quote}`;
          break;
        case 'clearFormatting':
          formattedText = selectedText.replace(/\\[a-z]+\{([^}]+)\}/g, '$1');
          break;
      }
      if (formattedText !== '') {
        const id = { major: 1, minor: 1 };
        const op = {
          identifier: id,
          range: selection,
          text: formattedText,
          forceMoveMarkers: true
        };
        editor.executeEdits('formatting', [op]);
        editor.focus();
      }
    }
    
    function openListingModal() {
      // Create the listing editor if it does not exist yet
      if (!listingEditor && document.getElementById('listing-code-editor')) {
        require(['vs/editor/editor.main'], function() {
          listingEditor = monaco.editor.create(document.getElementById('listing-code-editor'), {
            value: '# Example code\nimport numpy as np\n\nx = np.linspace(0, 10, 100)\ny = np.sin(x)\n\nprint("Hello, World!")',
            language: 'python',
            theme: <?php echo $theme === 'dark' ? '"custom-dark"' : '"custom-light"'; ?>,
            automaticLayout: true,
            fontSize: 14,
            lineNumbers: 'on',
            minimap: { enabled: false },
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            tabSize: 4
          });
          
          // Add a content change handler to update the preview
          listingEditor.onDidChangeModelContent(function() {
            updateListingPreview();
          });

          // Same transliteration rules as the main editor.
          window.cloudMonacoShared.installTransliteration({
            editor: listingEditor,
            container: document.getElementById('listing-code-editor'),
            monaco: monaco,
            getLang: function () { return window.cloudMonacoShared.resolveLang(<?php echo json_encode($lang); ?>); }
          });
          
          // Initial preview update
          setTimeout(updateListingPreview, 100);
        });
      } else if (listingEditor) {
        // If the editor already exists, update its content
        listingEditor.setValue('# Example code\nimport numpy as np\n\nx = np.linspace(0, 10, 100)\ny = np.sin(x)\n\nprint("Hello, World!")');
        
        // Force the preview refresh
        setTimeout(updateListingPreview, 100);
      }
      
      // Add handlers for all controls
      setupListingEventListeners();
      
      document.getElementById('listing-modal').style.display = 'flex';
    }

    function setupListingEventListeners() {
      // List of all elements whose changes should update the preview
      const elements = [
        'listing-language',
        'listing-font-family',
        'listing-font-size',
        'listing-tabsize',
        'listing-keyword-color',
        'listing-comment-color',
        'listing-string-color',
        'listing-background',
        'listing-frame',
        'listing-numbers',
        'listing-number-style',
        'listing-stepnumber',
        'listing-captionpos',
        'listing-breaklines',
        'listing-showspaces',
        'listing-showtabs',
        'listing-showstringspaces'
      ];
      
      // Remove old handlers, if any
      elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.removeEventListener('change', updateListingPreview);
          element.removeEventListener('input', updateListingPreview);
        }
      });
      
      // Add new handlers
      elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          if (element.type === 'checkbox') {
            element.addEventListener('change', updateListingPreview);
          } else if (element.type === 'color') {
            element.addEventListener('input', updateListingPreview);
            element.addEventListener('change', updateListingPreview);
          } else {
            element.addEventListener('change', updateListingPreview);
            element.addEventListener('input', updateListingPreview);
          }
        }
      });
    }

    function updateListingPreview() {
      if (!listingEditor) return;
      
      const code = listingEditor.getValue();
      const previewElement = document.getElementById('listing-preview-code');
      if (!previewElement) return;
      
      // Get all current settings for display in the preview
      const language = document.getElementById('listing-language')?.value || 'Python';
      const fontFamily = document.getElementById('listing-font-family')?.value || 'ttfamily';
      const fontSize = document.getElementById('listing-font-size')?.value || 'small';
      const keywordColor = document.getElementById('listing-keyword-color')?.value || '#0000ff';
      const commentColor = document.getElementById('listing-comment-color')?.value || '#008000';
      const stringColor = document.getElementById('listing-string-color')?.value || '#ff0000';
      const backgroundColor = document.getElementById('listing-background')?.value || '#f0f0f0';
      const frame = document.getElementById('listing-frame')?.value || 'single';
      const numbers = document.getElementById('listing-numbers')?.value || 'left';
      const tabsize = document.getElementById('listing-tabsize')?.value || 4;
      const breaklines = document.getElementById('listing-breaklines')?.checked || false;
      
      // Create an informative preview with the current settings
      let previewText = `// ${t['listing_preview'] || 'Code Preview'} (${language})\n`;
      previewText += `// ${t['listing_font_family']}: ${fontFamily}, ${t['listing_font_size']}: ${fontSize}\n`;
      previewText += `// ${t['listing_frame']}: ${frame}, ${t['listing_numbers']}: ${numbers}\n`;
      previewText += `// ${t['listing_tabsize']}: ${tabsize}, ${t['listing_breaklines']}: ${breaklines ? 'Yes' : 'No'}\n`;
      previewText += `// ${t['listing_keyword_color']}: ${keywordColor}\n`;
      previewText += `// ${t['listing_comment_color']}: ${commentColor}\n`;
      previewText += `// ${t['listing_string_color']}: ${stringColor}\n`;
      previewText += `// ${t['listing_background']}: ${backgroundColor}\n`;
      previewText += `// ${'='.repeat(50)}\n\n`;
      previewText += code;
      
      previewElement.textContent = previewText;
      
      // Add a small delay for visual confirmation of the update
      previewElement.style.transition = 'background-color 0.2s';
      previewElement.style.backgroundColor = 'rgba(var(--accent-color-rgb), 0.1)';
      setTimeout(() => {
        previewElement.style.backgroundColor = '';
      }, 200);
    }
    
    function updateListingPreview() {
      setTimeout(() => {
        if (!listingEditor) return;
        const code = listingEditor.getValue();
        const previewElement = document.getElementById('listing-preview-code');
        if (previewElement) {
          previewElement.textContent = code;
        }
      }, 100);
    }
    
    function copyListingCode() {
      if (!listingEditor) return;
      const code = listingEditor.getValue();
      copyTextToClipboard(code).then(() => {
        showNotification(t['copy'], t['copy_success'], 'success', 2000);
      }).catch(() => {
        showNotification(t['copy'], t['copy_error'], 'error', 2000);
      });
    }
    
    function insertListing() {
      if (!editor) return;
      
      const code = listingEditor ? listingEditor.getValue() : '';
      const language = document.getElementById('listing-language').value;
      const caption = document.getElementById('listing-caption').value || 'Source code';
      const label = document.getElementById('listing-label').value || 'lst:code';
      const fontFamily = document.getElementById('listing-font-family').value;
      const fontSize = document.getElementById('listing-font-size').value;
      const tabSize = document.getElementById('listing-tabsize').value || 4;
      const keywordColor = document.getElementById('listing-keyword-color').value;
      const commentColor = document.getElementById('listing-comment-color').value;
      const stringColor = document.getElementById('listing-string-color').value;
      const backgroundColor = document.getElementById('listing-background').value;
      const frame = document.getElementById('listing-frame').value;
      const numbers = document.getElementById('listing-numbers').value;
      const numberStyle = document.getElementById('listing-number-style').value;
      const stepnumber = document.getElementById('listing-stepnumber').value || 1;
      const captionPos = document.getElementById('listing-captionpos').value;
      const breaklines = document.getElementById('listing-breaklines').checked;
      const showspaces = document.getElementById('listing-showspaces').checked;
      const showtabs = document.getElementById('listing-showtabs').checked;
      const showstringspaces = document.getElementById('listing-showstringspaces').checked;
      
      const model = editor.getModel();
      const content = model.getValue();
      
      // Check for packages in the preamble
      const hasListings = /\\usepackage\s*(?:\[[^\]]*\])?\s*\{listings\}/.test(content);
      const hasXcolor = /\\usepackage\s*(?:\[[^\]]*\])?\s*\{xcolor\}/.test(content);
      
      // Create the listing settings
      let lstsetContent = '';
      
      lstsetContent += `language=${language},\n`;
      
      if (fontFamily === 'ttfamily') {
        lstsetContent += `    basicstyle=\\ttfamily\\${fontSize},\n`;
      } else if (fontFamily === 'rmfamily') {
        lstsetContent += `    basicstyle=\\rmfamily\\${fontSize},\n`;
      } else if (fontFamily === 'sffamily') {
        lstsetContent += `    basicstyle=\\sffamily\\${fontSize},\n`;
      }
      
      lstsetContent += `    keywordstyle=\\color[HTML]{${keywordColor.substring(1)}},\n`;
      lstsetContent += `    commentstyle=\\color[HTML]{${commentColor.substring(1)}},\n`;
      lstsetContent += `    stringstyle=\\color[HTML]{${stringColor.substring(1)}},\n`;
      
      if (numbers !== 'none') {
        lstsetContent += `    numbers=${numbers},\n`;
        lstsetContent += `    numberstyle=\\${numberStyle}\\color{gray},\n`;
        lstsetContent += `    stepnumber=${stepnumber},\n`;
        lstsetContent += `    numbersep=5pt,\n`;
      }
      
      lstsetContent += `    backgroundcolor=\\color[HTML]{${backgroundColor.substring(1)}},\n`;
      
      if (frame !== 'none') {
        lstsetContent += `    frame=${frame},\n`;
        lstsetContent += `    framesep=5pt,\n`;
        lstsetContent += `    rulecolor=\\color{black},\n`;
      }
      
      lstsetContent += `    captionpos=${captionPos},\n`;
      lstsetContent += `    showspaces=${showspaces ? 'true' : 'false'},\n`;
      lstsetContent += `    showtabs=${showtabs ? 'true' : 'false'},\n`;
      lstsetContent += `    showstringspaces=${showstringspaces ? 'true' : 'false'},\n`;
      lstsetContent += `    tabsize=${tabSize},\n`;
      lstsetContent += `    breaklines=${breaklines ? 'true' : 'false'},\n`;
      lstsetContent += `    breakatwhitespace=true,\n`;
      lstsetContent += `    escapeinside={\\%*}{*)},\n`;
      
      // The listing itself (will be inserted at the cursor position)
      const listingCode = `\\begin{lstlisting}[caption={${caption}}, label=${label}]\n${code}\n\\end{lstlisting}`;
      
      // If packages are needed, insert them right after \documentclass
      if (!hasListings || !hasXcolor) {
        // Find \documentclass
        const docClassMatch = content.match(/\\documentclass(?:\[[^\]]*\])?\s*\{[^}]*\}/);
        
        if (docClassMatch) {
          // Find the position after \documentclass
          const docClassEnd = docClassMatch.index + docClassMatch[0].length;
          
          // Determine the line number where \documentclass ends
          const textBeforeDocClass = content.substring(0, docClassEnd);
          const lineNumber = (textBeforeDocClass.match(/\n/g) || []).length + 1;
          
          // Determine the insertion position (end of the \documentclass line)
          const lineContent = model.getLineContent(lineNumber);
          const columnPosition = lineContent.length + 1;
          
          let packagesToInsert = '\n';
          if (!hasListings) {
            packagesToInsert += '\\usepackage{listings}\n';
          }
          if (!hasXcolor) {
            packagesToInsert += '\\usepackage{xcolor}\n';
          }
          
          // Create an operation to insert packages right after \documentclass
          const packagesRange = new monaco.Range(lineNumber, columnPosition, lineNumber, columnPosition);
          
          // Insert settings and listing at the cursor position
          const selection = editor.getSelection();
          const fullListingText = `\n\n% Настройки листинга\n\\lstset{\n${lstsetContent}}\n\n${listingCode}`;
          
          const operations = [
            {
              identifier: { major: 1, minor: 1 },
              range: packagesRange,
              text: packagesToInsert,
              forceMoveMarkers: true
            },
            {
              identifier: { major: 1, minor: 2 },
              range: selection,
              text: fullListingText,
              forceMoveMarkers: true
            }
          ];
          
          editor.executeEdits('insert-listing-full', operations);
        } else {
          // If \documentclass cannot be found, insert everything at the cursor position with explanations
          let packagesText = '';
          if (!hasListings) {
            packagesText += '\\usepackage{listings}\n';
          }
          if (!hasXcolor) {
            packagesText += '\\usepackage{xcolor}\n';
          }
          
          const fullText = `% Добавьте эти пакеты после \\documentclass:\n${packagesText}\n\\lstset{\n${lstsetContent}}\n\n${listingCode}`;
          
          const selection = editor.getSelection();
          const op = {
            identifier: { major: 1, minor: 1 },
            range: selection,
            text: fullText,
            forceMoveMarkers: true
          };
          editor.executeEdits('insert-listing', [op]);
        }
      } else {
        // Packages already exist, insert only settings and listing at the cursor position
        const fullText = `\n\n\\lstset{\n${lstsetContent}}\n\n${listingCode}`;
        
        const selection = editor.getSelection();
        const op = {
          identifier: { major: 1, minor: 1 },
          range: selection,
          text: fullText,
          forceMoveMarkers: true
        };
        editor.executeEdits('insert-listing', [op]);
      }
      
      editor.focus();
      closeModal('listing-modal');
      showNotification(t['listing_insert'], t['element_inserted'], 'success', 2000);
    }
    
    document.getElementById('compile-btn').addEventListener('click', () => compileLatex(false));
    document.getElementById('save-pdf-btn').addEventListener('click', () => compileLatex(true));
    document.getElementById('mobile-compile-btn').addEventListener('click', () => compileLatex(false));
    document.getElementById('mobile-save-pdf-btn').addEventListener('click', () => compileLatex(true));
    document.getElementById('mobile-insert-btn').addEventListener('click', function() {
      document.body.classList.toggle('mobile-insert-visible');
    });
    document.querySelectorAll('.mobile-insert-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const elementType = this.getAttribute('data-value');
        insertElement(elementType);
        document.body.classList.remove('mobile-insert-visible');
      });
    });
    document.addEventListener('click', function(e) {
      if (!e.target.closest('#mobile-insert-menu') && !e.target.closest('#mobile-insert-btn')) {
        document.body.classList.remove('mobile-insert-visible');
      }
    });
    resizer.addEventListener('mousedown', startResize);
    resizer.addEventListener('touchstart', startResize, { passive: false });
    window.addEventListener('load', function() {
      fetch('?cleanup_temp=1')
        .then(response => response.text())
        .then(data => {
          // Temp cleanup completed silently
        })
        .catch(err => {
          // Temp cleanup error - silent
        });
      if (isMobile && !isFullscreen && !isTablet) {
            document.getElementById('formatting-toolbar').style.display = 'none';
        }
      initDropdowns();
      setupModalEvents();
      initSymbolsModal();
      initTableEditor();
      initFormattingToolbar();
      updateFormattingButtonsState();
      setStatus(t['ready'], 'status-ok');
    });
    setStatus(t['ready'], 'status-ok');
    scheduleRelayout();
  
