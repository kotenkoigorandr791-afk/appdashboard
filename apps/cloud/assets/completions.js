// completions.js
// Hint database for CodeMirror autocomplete in different languages

window.completions = {
    matlab: {
        keywords: [
            'function', 'end', 'if', 'else', 'elseif', 'for', 'while', 'switch', 'case', 'otherwise', 
            'break', 'continue', 'return', 'try', 'catch', 'classdef', 'properties', 'methods', 
            'global', 'persistent', 'parfor', 'spmd', 'import', 'enum', 'events', 'arguments', 
            'try', 'catch', 'throw', 'assert', 'clear', 'eval', 'feval', 'tic', 'toc', 'profile', 
            'persistent', 'global', 'local', 'script', 'endfunction', 'endwhile', 'endif', 'endfor', 
            'endswitch', 'endclassdef', 'endmethods', 'endproperties'
        ],
        builtins: [
            'sin', 'cos', 'tan', 'exp', 'log', 'log10', 'sqrt', 'abs', 'min', 'max', 'mean', 'sum', 
            'size', 'length', 'disp', 'plot', 'figure', 'hold', 'grid', 'title', 'xlabel', 'ylabel', 
            'legend', 'subplot', 'axis', 'clf', 'cla', 'close', 'zeros', 'ones', 'eye', 'rand', 
            'randn', 'linspace', 'logspace', 'meshgrid', 'fft', 'ifft', 'conv', 'deconv', 'filter', 
            'ode45', 'lsim', 'step', 'impulse', 'surf', 'mesh', 'contour', 'imshow', 'imread', 
            'imwrite', 'save', 'load', 'csvread', 'csvwrite', 'format', 'who', 'whos', 'clearvars', 
            'numel', 'isnan', 'isinf', 'isequal', 'strcmp', 'find', 'sort', 'unique'
        ],
        operators: [
            '+', '-', '*', '/', '\\', '^', '.*', './', '.\\', '.^', '==', '~=', '<', '>', '<=', '>=', 
            '&', '|', '~', '&&', '||', '=', ':', '.'
        ]
    },
    javascript: {
        keywords: [
            'function', 'if', 'else', 'for', 'while', 'do', 'switch', 'case', 'break', 'continue', 
            'return', 'var', 'let', 'const', 'class', 'extends', 'import', 'export', 'default', 'from', 
            'async', 'await', 'try', 'catch', 'finally', 'throw', 'new', 'this', 'super', 'typeof', 
            'instanceof', 'in', 'of', 'void', 'delete', 'with', 'yield', 'debugger', 'static', 'get', 
            'set', 'private', 'public', 'protected', 'interface', 'implements', 'package', 'enum', 
            'break', 'continue', 'return', 'await', 'async', 'export', 'import', 'namespace'
        ],
        builtins: [
            'console', 'document', 'window', 'navigator', 'Math', 'Date', 'Array', 'Object', 'String', 
            'Number', 'Boolean', 'Function', 'RegExp', 'Error', 'Promise', 'Set', 'Map', 'JSON', 
            'localStorage', 'sessionStorage', 'WeakSet', 'WeakMap', 'Symbol', 'BigInt', 'Reflect', 
            'Proxy', 'Intl', 'ArrayBuffer', 'DataView', 'Float32Array', 'Float64Array', 'Int8Array', 
            'Int16Array', 'Int32Array', 'Uint8Array', 'Uint16Array', 'Uint32Array', 'Map', 'Set', 
            'WeakMap', 'WeakSet', 'fetch', 'alert', 'prompt', 'confirm', 'setTimeout', 'setInterval', 
            'clearTimeout', 'clearInterval', 'performance', 'history', 'location'
        ],
        methods: [
            'log', 'warn', 'error', 'info', 'debug', 'getElementById', 'getElementsByClassName', 
            'querySelector', 'querySelectorAll', 'addEventListener', 'removeEventListener', 'toString', 
            'valueOf', 'push', 'pop', 'shift', 'unshift', 'slice', 'splice', 'forEach', 'map', 'filter', 
            'reduce', 'find', 'includes', 'indexOf', 'lastIndexOf', 'join', 'concat', 'reverse', 'sort', 
            'every', 'some', 'startsWith', 'endsWith', 'trim', 'substring', 'replace', 'split', 'match', 
            'search', 'toLowerCase', 'toUpperCase', 'bind', 'call', 'apply', 'then', 'catch', 'finally', 
            'setAttribute', 'getAttribute', 'removeAttribute', 'createElement', 'appendChild', 
            'removeChild', 'replaceChild', 'add', 'delete', 'has', 'keys', 'values', 'entries'
        ]
    },
    html: {
        tags: [
            'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'ul', 'ol', 'li', 
            'table', 'tr', 'td', 'th', 'form', 'input', 'button', 'textarea', 'select', 'option', 
            'label', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main', 'style', 
            'script', 'link', 'meta', 'title', 'head', 'body', 'canvas', 'svg', 'audio', 'video', 
            'iframe', 'figure', 'figcaption', 'picture', 'source', 'track', 'embed', 'object', 'param', 
            'details', 'summary', 'dialog', 'menu', 'menuitem', 'time', 'mark', 'progress', 'meter', 
            'base', 'br', 'hr', 'pre', 'code', 'kbd', 'samp', 'var', 'address', 'blockquote', 'q'
        ],
        attributes: [
            'class', 'id', 'style', 'src', 'href', 'alt', 'title', 'width', 'height', 'type', 'value', 
            'name', 'placeholder', 'required', 'disabled', 'readonly', 'checked', 'selected', 'onclick', 
            'onchange', 'onkeyup', 'onkeydown', 'onload', 'onerror', 'onfocus', 'onblur', 'onsubmit', 
            'oninput', 'onselect', 'onresize', 'onscroll', 'onmouseover', 'onmouseout', 'onmousemove', 
            'lang', 'dir', 'tabindex', 'contenteditable', 'spellcheck', 'draggable', 'dropzone', 
            'accesskey', 'hidden', 'data-*', 'role', 'aria-label', 'aria-hidden', 'aria-describedby', 
            'accept', 'action', 'method', 'enctype', 'target', 'rel', 'media', 'sizes', 'srcset', 
            'autoplay', 'loop', 'muted', 'controls', 'preload'
        ]
    },
    css: {
        properties: [
            'color', 'background', 'background-color', 'background-image', 'background-position', 
            'background-size', 'background-repeat', 'font-size', 'font-family', 'font-weight', 
            'font-style', 'text-align', 'margin', 'margin-top', 'margin-right', 'margin-bottom', 
            'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 
            'border', 'border-top', 'border-right', 'border-bottom', 'border-left', 'border-width', 
            'border-style', 'border-color', 'width', 'height', 'display', 'position', 'top', 'left', 
            'right', 'bottom', 'float', 'clear', 'overflow', 'visibility', 'opacity', 'z-index', 'flex', 
            'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-content', 'grid', 
            'grid-template-columns', 'grid-template-rows', 'grid-gap', 'transition', 'transition-property', 
            'transition-duration', 'transition-timing-function', 'animation', 'animation-name', 
            'animation-duration', 'animation-timing-function', 'transform', 'transform-origin', 
            'box-shadow', 'text-shadow', 'border-radius', 'outline', 'cursor', 'user-select', 
            'pointer-events', 'gap', 'column-gap', 'row-gap'
        ],
        values: [
            'none', 'block', 'inline', 'inline-block', 'flex', 'grid', 'absolute', 'relative', 'fixed', 
            'static', 'auto', 'inherit', 'initial', 'unset', 'bold', 'italic', 'underline', 'center', 
            'left', 'right', 'justify', 'solid', 'dashed', 'dotted', 'hidden', 'visible', 'scroll', 
            'auto', 'no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'cover', 'contain', 'space-between', 
            'space-around', 'space-evenly', 'stretch', 'flex-start', 'flex-end', 'center', 'baseline', 
            'normal', 'uppercase', 'lowercase', 'capitalize', 'transparent', 'currentColor'
        ],
        units: [
            'px', 'em', 'rem', '%', 'vh', 'vw', 'vmin', 'vmax', 'cm', 'mm', 'in', 'pt', 'pc', 'ex', 
            'ch', 'fr', 'deg', 'rad', 'turn', 'ms', 's'
        ]
    },
    python: {
        keywords: [
            'def', 'class', 'if', 'else', 'elif', 'for', 'while', 'break', 'continue', 'return', 
            'import', 'from', 'as', 'try', 'except', 'finally', 'raise', 'with', 'lambda', 'yield', 
            'async', 'await', 'global', 'nonlocal', 'assert', 'del', 'pass', 'in', 'is', 'and', 'or', 
            'not', 'True', 'False', 'None', 'match', 'case', 'async', 'await', 'def', 'class', 
            'with', 'try', 'except', 'finally', 'raise', 'lambda', 'yield', 'global', 'nonlocal'
        ],
        builtins: [
            'print', 'len', 'range', 'list', 'dict', 'set', 'tuple', 'str', 'int', 'float', 'bool', 
            'type', 'isinstance', 'issubclass', 'super', 'enumerate', 'zip', 'map', 'filter', 'reduce', 
            'sum', 'min', 'max', 'sorted', 'reversed', 'abs', 'all', 'any', 'chr', 'ord', 'hex', 
            'oct', 'bin', 'dir', 'id', 'input', 'open', 'format', 'round', 'slice', 'vars', 'locals', 
            'globals', 'next', 'iter', 'hasattr', 'getattr', 'setattr', 'delattr', 'callable', 
            'compile', 'exec', 'eval', 'divmod', 'hash', 'memoryview', 'object', 'bytearray', 'bytes'
        ],
        methods: [
            'append', 'extend', 'insert', 'remove', 'pop', 'clear', 'index', 'count', 'sort', 'reverse', 
            'keys', 'values', 'items', 'get', 'update', 'add', 'discard', 'union', 'intersection', 
            'difference', 'symmetric_difference', 'issubset', 'issuperset', 'isdisjoint', 'copy', 
            'split', 'join', 'strip', 'lstrip', 'rstrip', 'replace', 'find', 'rfind', 'startswith', 
            'endswith', 'lower', 'upper', 'title', 'capitalize', 'format', 'encode', 'decode', 
            'isalpha', 'isdigit', 'isalnum', 'isspace', 'islower', 'isupper', 'zfill', 'partition'
        ],
        modules: [
            'os', 'sys', 'math', 'random', 'datetime', 'time', 'json', 're', 'csv', 'pickle', 
            'subprocess', 'pathlib', 'collections', 'itertools', 'functools', 'operator', 'logging', 
            'argparse', 'shutil', 'glob', 'tempfile', 'uuid', 'hashlib', 'socket', 'threading', 
            'multiprocessing', 'asyncio', 'requests', 'urllib', 'pandas', 'numpy', 'matplotlib'
        ]
    },
    php: {
        keywords: [
            '<?php', '?>', 'function', 'class', 'if', 'else', 'elseif', 'for', 'foreach', 'while', 
            'do', 'switch', 'case', 'break', 'continue', 'return', 'new', 'this', 'self', 'parent', 
            'public', 'private', 'protected', 'static', 'abstract', 'final', 'interface', 'implements', 
            'extends', 'namespace', 'use', 'as', 'try', 'catch', 'finally', 'throw', 'const', 'trait', 
            'global', 'goto', 'include', 'include_once', 'require', 'require_once', 'declare', 'yield', 
            'insteadof', 'enum', 'match', 'readonly'
        ],
        functions: [
            'echo', 'print', 'isset', 'empty', 'unset', 'array', 'count', 'sizeof', 'in_array', 
            'array_push', 'array_pop', 'array_shift', 'array_unshift', 'explode', 'implode', 
            'strlen', 'substr', 'strpos', 'str_replace', 'preg_match', 'json_encode', 'json_decode', 
            'file_get_contents', 'file_put_contents', 'array_merge', 'array_keys', 'array_values', 
            'array_unique', 'array_filter', 'array_map', 'array_reduce', 'strtolower', 'strtoupper', 
            'trim', 'ltrim', 'rtrim', 'sprintf', 'printf', 'var_dump', 'print_r', 'serialize', 
            'unserialize', 'is_array', 'is_string', 'is_int', 'is_float', 'is_bool', 'is_object', 
            'is_null', 'gettype', 'settype', 'header', 'http_response_code', 'session_start', 
            'session_destroy', 'mysqli_connect', 'mysqli_query', 'mysqli_fetch_assoc'
        ],
        superglobals: [
            '$_GET', '$_POST', '$_REQUEST', '$_SESSION', '$_COOKIE', '$_FILES', '$_SERVER', 
            '$GLOBALS', '$_ENV'
        ],
        constants: [
            'PHP_VERSION', 'PHP_OS', 'PHP_EOL', 'NULL', 'TRUE', 'FALSE', '__FILE__', '__LINE__', 
            '__DIR__', '__FUNCTION__', '__CLASS__', '__METHOD__', '__NAMESPACE__'
        ]
    },
    sql: {
        keywords: [
            'SELECT', 'FROM', 'WHERE', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 
            'TABLE', 'DATABASE', 'INDEX', 'VIEW', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON', 
            'AND', 'OR', 'NOT', 'IN', 'LIKE', 'BETWEEN', 'IS', 'NULL', 'ORDER BY', 'GROUP BY', 
            'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'DISTINCT', 'ALL', 'ANY', 'SOME', 'EXISTS', 
            'UNIQUE', 'PRIMARY KEY', 'FOREIGN KEY', 'REFERENCES', 'CHECK', 'CONSTRAINT', 'TRIGGER', 
            'PROCEDURE', 'FUNCTION', 'WITH', 'AS', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END', 'OVER', 
            'PARTITION BY', 'ROW_NUMBER', 'RANK', 'DENSE_RANK'
        ],
        functions: [
            'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'UPPER', 'LOWER', 'SUBSTRING', 'CONCAT', 'COALESCE', 
            'NOW', 'CURDATE', 'CURTIME', 'DATE_FORMAT', 'CAST', 'CONVERT', 'LENGTH', 'TRIM', 'LTRIM', 
            'RTRIM', 'ROUND', 'FLOOR', 'CEIL', 'ABS', 'POWER', 'MOD', 'GREATEST', 'LEAST', 'TO_DATE', 
            'TO_CHAR', 'DATEDIFF', 'DATEADD', 'EXTRACT', 'SYSDATE', 'GETDATE', 'IFNULL', 'NVL', 
            'ISNULL', 'STR_TO_DATE', 'FORMAT', 'REGEXP_LIKE', 'REGEXP_REPLACE', 'GROUP_CONCAT'
        ],
        types: [
            'INT', 'INTEGER', 'BIGINT', 'SMALLINT', 'TINYINT', 'FLOAT', 'DOUBLE', 'DECIMAL', 
            'NUMERIC', 'CHAR', 'VARCHAR', 'TEXT', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'BOOLEAN'
        ]
    },
    java: {
        keywords: [
            'public', 'private', 'protected', 'class', 'interface', 'abstract', 'final', 'static', 
            'void', 'int', 'double', 'float', 'char', 'boolean', 'byte', 'short', 'long', 'if', 'else', 
            'for', 'while', 'do', 'switch', 'case', 'default', 'break', 'continue', 'return', 'new', 
            'this', 'super', 'try', 'catch', 'finally', 'throw', 'throws', 'import', 'package', 
            'extends', 'implements', 'instanceof', 'volatile', 'transient', 'synchronized', 'native', 
            'strictfp', 'enum', 'assert', 'goto', 'const'
        ],
        builtins: [
            'System', 'String', 'Object', 'Integer', 'Double', 'Float', 'Character', 'Boolean', 'Byte', 
            'Short', 'Long', 'Math', 'Arrays', 'Collections', 'List', 'ArrayList', 'LinkedList', 'Set', 
            'HashSet', 'TreeSet', 'Map', 'HashMap', 'TreeMap', 'Optional', 'Stream', 'Thread', 
            'Runnable', 'Exception', 'RuntimeException', 'File', 'Path', 'Files', 'InputStream', 
            'OutputStream', 'Reader', 'Writer', 'BufferedReader', 'BufferedWriter', 'Date', 'LocalDate', 
            'LocalTime', 'LocalDateTime', 'Instant', 'Random'
        ],
        methods: [
            'toString', 'equals', 'hashCode', 'getClass', 'clone', 'finalize', 'wait', 'notify', 
            'notifyAll', 'add', 'remove', 'contains', 'size', 'isEmpty', 'get', 'put', 'keySet', 
            'values', 'entrySet', 'stream', 'forEach', 'map', 'filter', 'reduce', 'collect', 'sorted', 
            'println', 'print', 'readLine', 'write', 'flush', 'close', 'exists', 'createNewFile', 
            'delete', 'mkdir', 'listFiles', 'getAbsolutePath', 'format', 'parseInt', 'parseDouble', 
            'valueOf', 'compareTo', 'substring', 'indexOf', 'lastIndexOf', 'replace', 'split'
        ]
    },
    cpp: {
        keywords: [
            'int', 'float', 'double', 'char', 'void', 'bool', 'long', 'short', 'signed', 'unsigned', 
            'if', 'else', 'for', 'while', 'do', 'switch', 'case', 'break', 'continue', 'return', 
            'class', 'struct', 'union', 'enum', 'public', 'private', 'protected', 'static', 'const', 
            'volatile', 'mutable', 'friend', 'virtual', 'inline', 'explicit', 'override', 'final', 
            'namespace', 'using', 'template', 'typename', 'decltype', 'auto', 'static_cast', 
            'dynamic_cast', 'const_cast', 'reinterpret_cast', 'try', 'catch', 'throw', 'new', 'delete'
        ],
        builtins: [
            'cout', 'cin', 'cerr', 'clog', 'string', 'vector', 'array', 'map', 'unordered_map', 'set', 
            'unordered_set', 'queue', 'priority_queue', 'stack', 'deque', 'list', 'forward_list', 
            'pair', 'tuple', 'iostream', 'fstream', 'sstream', 'algorithm', 'numeric', 'functional', 
            'memory', 'thread', 'mutex', 'condition_variable', 'chrono', 'random', 'bitset', 'complex', 
            'valarray', 'exception', 'stdexcept', 'assert', 'limits', 'cmath', 'cstdlib', 'cstring'
        ],
        methods: [
            'push_back', 'pop_back', 'emplace_back', 'insert', 'erase', 'clear', 'size', 'empty', 
            'begin', 'end', 'rbegin', 'rend', 'front', 'back', 'at', 'find', 'count', 'lower_bound', 
            'upper_bound', 'sort', 'reverse', 'accumulate', 'max_element', 'min_element', 'next', 
            'prev', 'swap', 'fill', 'copy', 'move', 'replace', 'getline', 'read', 'write', 'open', 
            'close', 'flush', 'sin', 'cos', 'tan', 'sqrt', 'abs', 'pow', 'rand', 'srand', 'lock', 
            'unlock', 'wait', 'notify_one', 'notify_all'
        ]
    },
    c: {
        keywords: [
            'int', 'float', 'double', 'char', 'void', 'long', 'short', 'signed', 'unsigned', 'if', 
            'else', 'for', 'while', 'do', 'switch', 'case', 'break', 'continue', 'return', 'struct', 
            'union', 'enum', 'static', 'const', 'volatile', 'register', 'auto', 'extern', 'typedef', 
            'goto', 'sizeof', 'inline', 'restrict', '_Bool', '_Complex', '_Imaginary'
        ],
        builtins: [
            'printf', 'scanf', 'sprintf', 'sscanf', 'fopen', 'fclose', 'fread', 'fwrite', 'fseek', 
            'ftell', 'rewind', 'getchar', 'putchar', 'puts', 'gets', 'malloc', 'calloc', 'realloc', 
            'free', 'memset', 'memcpy', 'memcmp', 'strcpy', 'strncpy', 'strcat', 'strncat', 'strcmp', 
            'strncmp', 'strlen', 'strchr', 'strrchr', 'strstr', 'atoi', 'atol', 'atof', 'rand', 
            'srand', 'time', 'clock', 'exit', 'abort', 'assert', 'math.h', 'string.h', 'stdlib.h', 
            'stdio.h', 'time.h'
        ],
        methods: [] // C typically doesn't use methods, so keep empty
    }
};