<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
class AdvancedCodeFormatter {
    
    private $indentSize = 4;
    
    // Keywords for each language
    private $keywords = [
        'php' => ['if', 'else', 'elseif', 'while', 'for', 'foreach', 'do', 'switch', 'case', 'default', 
                 'function', 'class', 'interface', 'trait', 'try', 'catch', 'finally', 'public', 'private', 
                 'protected', 'static', 'abstract', 'final', 'const', 'var', 'return', 'break', 'continue',
                 'namespace', 'use', 'require', 'include', 'require_once', 'include_once'],
        'c' => ['if', 'else', 'while', 'for', 'do', 'switch', 'case', 'default', 'int', 'char', 'float', 
               'double', 'void', 'struct', 'union', 'enum', 'typedef', 'static', 'extern', 'auto', 
               'register', 'const', 'volatile', 'return', 'break', 'continue', 'goto', 'sizeof'],
        'python' => ['if', 'elif', 'else', 'while', 'for', 'def', 'class', 'try', 'except', 'finally', 
                    'with', 'as', 'import', 'from', 'return', 'yield', 'pass', 'break', 'continue', 
                    'lambda', 'global', 'nonlocal', 'and', 'or', 'not', 'in', 'is'],
        'powershell' => ['if', 'else', 'elseif', 'while', 'for', 'foreach', 'do', 'switch', 'function',
                        'param', 'begin', 'process', 'end', 'try', 'catch', 'finally', 'throw', 'return',
                        'break', 'continue', 'where', 'select']
    ];
    
    // HTML tags that should be on a single line
    private $inlineTags = ['span', 'a', 'strong', 'b', 'i', 'em', 'small', 'mark', 'del', 'ins', 'sub', 'sup'];
    
    // HTML tags that don't require closing tags
    private $voidTags = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 
                        'param', 'source', 'track', 'wbr'];
    
    public function formatCode($code, $language) {
        switch(strtolower($language)) {
            case 'php':
                return $this->formatPHP($code);
            case 'html':
                return $this->formatHTML($code);
            case 'c':
            case 'c++':
            case 'cpp':
                return $this->formatC($code);
            case 'python':
                return $this->formatPython($code);
            case 'powershell':
                return $this->formatPowerShell($code);
            default:
                return $this->formatGeneric($code);
        }
    }
    
    private function formatPHP($code) {
        // First normalize whitespace
        $code = $this->normalizeWhitespace($code);
        
        // Handle special PHP constructs
        $code = $this->processPHPStructures($code);
        
        // Add line breaks
        $code = $this->addPHPLineBreaks($code);
        
        // Apply indentation
        return $this->addPHPIndentation($code);
    }
    
    private function processPHPStructures($code) {
        // Handle functions
        $code = preg_replace('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', "function $1(", $code);
        
        // Handle classes
        $code = preg_replace('/\bclass\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*/', "class $1 ", $code);
        
        // Handle conditionals
        $code = preg_replace('/\bif\s*\(/', "if(", $code);
        $code = preg_replace('/\}\s*else\s*\{/', "} else {", $code);
        $code = preg_replace('/\}\s*elseif\s*\(/', "} elseif(", $code);
        
        // Handle loops
        $code = preg_replace('/\bfor\s*\(/', "for(", $code);
        $code = preg_replace('/\bwhile\s*\(/', "while(", $code);
        $code = preg_replace('/\bforeach\s*\(/', "foreach(", $code);
        
        return $code;
    }
    
    private function addPHPLineBreaks($code) {
        // Add line breaks after opening braces
        $code = str_replace('{', "{\n", $code);
        
        // Add line breaks before closing braces
        $code = str_replace('}', "\n}", $code);
        
        // Add line breaks after semicolons
        $code = preg_replace('/;(?!\s*\n)/', ";\n", $code);
        
        // Special cases for else
        $code = preg_replace('/\n\s*}\s*else\s*\{\n/', "\n} else {\n", $code);
        $code = preg_replace('/\n\s*}\s*elseif/', "\n} elseif", $code);
        
        return $code;
    }
    
    private function addPHPIndentation($code) {
        $lines = explode("\n", $code);
        $formatted = [];
        $indentLevel = 0;
        
        foreach($lines as $line) {
            $trimmed = trim($line);
            if($trimmed === '') continue;
            
            // Determine indent level for the current line
            if(preg_match('/^}/', $trimmed)) {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            // Add indentation
            $indent = str_repeat(' ', $indentLevel * $this->indentSize);
            $formatted[] = $indent . $trimmed;
            
            // Increase indent for subsequent lines
            if(preg_match('/\{$/', $trimmed)) {
                $indentLevel++;
            }
        }
        
        return implode("\n", $formatted);
    }
    
    private function formatHTML($code) {
        // Normalize whitespace between tags
        $code = preg_replace('/>\s+</', '><', trim($code));
        
        // Handle special HTML structures
        $code = $this->processHTMLStructures($code);
        
        // Add line breaks
        $code = $this->addHTMLLineBreaks($code);
        
        // Apply indentation
        return $this->addHTMLIndentation($code);
    }
    
    private function processHTMLStructures($code) {
        // Handle comments
        $code = preg_replace('/<!--(.*?)-->/', "<!--$1-->", $code);
        
        // Handle DOCTYPE
        $code = preg_replace('/<!DOCTYPE\s+([^>]+)>/i', "<!DOCTYPE $1>", $code);
        
        return $code;
    }
    
    private function addHTMLLineBreaks($code) {
        // Tokenize code (tags and content)
        preg_match_all('/(<[^>]*>|[^<]+)/', $code, $matches);
        $tokens = $matches[0];
        
        $result = [];
        $previousWasOpenTag = false;
        
        foreach($tokens as $token) {
            $token = trim($token);
            if(empty($token)) continue;
            
            if(preg_match('/^<([^\/\s>]+)/', $token, $tagMatches)) {
                // Открывающий тег
                $tagName = strtolower($tagMatches[1]);
                
                if(!in_array($tagName, $this->inlineTags)) {
                    $result[] = $token;
                    $previousWasOpenTag = true;
                } else {
                    // Инлайн тег - не добавляем перенос
                    $result[] = $token;
                    $previousWasOpenTag = false;
                }
            } elseif(preg_match('/^<\/([^>]+)>/', $token, $tagMatches)) {
                // Закрывающий тег
                $tagName = strtolower($tagMatches[1]);
                
                if(!in_array($tagName, $this->inlineTags)) {
                    $result[] = $token;
                } else {
                    $result[] = $token;
                }
                $previousWasOpenTag = false;
            } elseif(preg_match('/^<[^>]*\/>/', $token)) {
                // Самозакрывающийся тег
                $result[] = $token;
                $previousWasOpenTag = false;
            } else {
                // Текстовое содержимое
                $result[] = $token;
                $previousWasOpenTag = false;
            }
        }
        
        return implode('', $result);
    }
    
    private function addHTMLIndentation($code) {
        // Re-tokenize for correct indentation handling
        preg_match_all('/(<[^>]*>|[^<]+)/', $code, $matches);
        $tokens = $matches[0];
        
        $formatted = [];
        $indentLevel = 0;
        $currentLine = '';
        
        foreach($tokens as $token) {
            $token = trim($token);
            if(empty($token)) continue;
            
            if(preg_match('/^<([^\/\s>]+)/', $token, $tagMatches)) {
                // Открывающий тег
                $tagName = strtolower($tagMatches[1]);
                
                if(!in_array($tagName, $this->inlineTags)) {
                    // Добавляем текущую строку если есть
                    if(!empty($currentLine)) {
                        $formatted[] = str_repeat(' ', $indentLevel * $this->indentSize) . $currentLine;
                        $currentLine = '';
                    }
                    
                    // Добавляем тег на новой строке
                    $formatted[] = str_repeat(' ', $indentLevel * $this->indentSize) . $token;
                    
                    // Увеличиваем отступ если это не самозакрывающийся тег
                    if(!in_array($tagName, $this->voidTags) && !preg_match('/\/>$/', $token)) {
                        $indentLevel++;
                    }
                } else {
                    // Инлайн тег добавляем к текущей строке
                    $currentLine .= $token;
                }
            } elseif(preg_match('/^<\/([^>]+)>/', $token, $tagMatches)) {
                // Закрывающий тег
                $tagName = strtolower($tagMatches[1]);
                
                if(!in_array($tagName, $this->inlineTags)) {
                    // Добавляем текущую строку если есть
                    if(!empty($currentLine)) {
                        $formatted[] = str_repeat(' ', $indentLevel * $this->indentSize) . $currentLine;
                        $currentLine = '';
                    }
                    
                    // Уменьшаем отступ
                    $indentLevel = max(0, $indentLevel - 1);
                    
                    // Добавляем закрывающий тег
                    $formatted[] = str_repeat(' ', $indentLevel * $this->indentSize) . $token;
                } else {
                    // Инлайн закрывающий тег
                    $currentLine .= $token;
                }
            } else {
                // Текстовое содержимое
                $currentLine .= $token;
            }
        }
        
        // Добавляем последнюю строку если есть
        if(!empty($currentLine)) {
            $formatted[] = str_repeat(' ', $indentLevel * $this->indentSize) . $currentLine;
        }
        
        return implode("\n", $formatted);
    }
    
    private function formatC($code) {
        // Нормализуем пробелы
        $code = $this->normalizeWhitespace($code);
        
        // Обрабатываем C структуры
        $code = $this->processCStructures($code);
        
        // Add line breaks
        $code = $this->addCLineBreaks($code);
        
        // Apply indentation
        return $this->addCIndentation($code);
    }
    
    private function processCStructures($code) {
        // Обрабатываем включения
        $code = preg_replace('/#include\s*<([^>]+)>/', "#include <$1>", $code);
        $code = preg_replace('/#include\s*"([^"]+)"/', '#include "$1"', $code);
        
        // Handle functions
        $code = preg_replace('/\b(int|void|char|float|double)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', "$1 $2(", $code);
        
        // Handle loops и условия
        $code = preg_replace('/\bif\s*\(/', "if(", $code);
        $code = preg_replace('/\bfor\s*\(/', "for(", $code);
        $code = preg_replace('/\bwhile\s*\(/', "while(", $code);
        $code = preg_replace('/\}\s*else\s*\{/', "} else {", $code);
        
        // Обрабатываем структуры
        $code = preg_replace('/\bstruct\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\{/', "struct $1 {", $code);
        
        return $code;
    }
    
    private function addCLineBreaks($code) {
        // Добавляем переносы после препроцессорных директив
        $code = preg_replace('/(#[^\n]*)/m', "$1\n", $code);
        
        // Добавляем переносы для функций
        $code = preg_replace('/\b(int|void|char|float|double)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{/', "$1 $2() {\n", $code);
        
        // Добавляем переносы для основных конструкций
        $code = str_replace('{', "{\n", $code);
        $code = str_replace('}', "\n}", $code);
        
        // Add line breaks after semicolons
        $code = preg_replace('/;(?!\s*\n)/', ";\n", $code);
        
        // Обрабатываем return
        $code = preg_replace('/\breturn\s/', "\nreturn ", $code);
        
        // Исправляем else
        $code = preg_replace('/\n\s*}\s*else\s*\{\n/', "\n} else {\n", $code);
        
        return $code;
    }
    
    private function addCIndentation($code) {
        $lines = explode("\n", $code);
        $formatted = [];
        $indentLevel = 0;
        
        foreach($lines as $line) {
            $trimmed = trim($line);
            if($trimmed === '') continue;
            
            // Препроцессорные директивы не имеют отступов
            if(preg_match('/^#/', $trimmed)) {
                $formatted[] = $trimmed;
                continue;
            }
            
            // Определяем уровень отступа
            if(preg_match('/^}/', $trimmed)) {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            // Add indentation
            $indent = str_repeat(' ', $indentLevel * $this->indentSize);
            $formatted[] = $indent . $trimmed;
            
            // Увеличиваем отступ после открывающих скобок
            if(preg_match('/\{$/', $trimmed)) {
                $indentLevel++;
            }
        }
        
        return implode("\n", $formatted);
    }
    
    private function formatPython($code) {
        // Нормализуем пробелы
        $code = $this->normalizeWhitespace($code);
        
        // Обрабатываем Python структуры
        $code = $this->processPythonStructures($code);
        
        // Add line breaks
        $code = $this->addPythonLineBreaks($code);
        
        // Apply indentation
        return $this->addPythonIndentation($code);
    }
    
    private function processPythonStructures($code) {
        // Обрабатываем импорты
        $code = preg_replace('/\bimport\s+([a-zA-Z_][a-zA-Z0-9_.]*)/', "import $1", $code);
        $code = preg_replace('/\bfrom\s+([a-zA-Z_][a-zA-Z0-9_.]*)\s+import/', "from $1 import", $code);
        
        // Handle functions
        $code = preg_replace('/\bdef\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', "def $1(", $code);
        
        // Handle classes
        $code = preg_replace('/\bclass\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', "class $1:", $code);
        $code = preg_replace('/\bclass\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*:/', "class $1():", $code);
        
        // Обрабатываем условия и циклы
        $code = preg_replace('/\bif\s+/', "if ", $code);
        $code = preg_replace('/\belif\s+/', "elif ", $code);
        $code = preg_replace('/\belse\s*:/', "else:", $code);
        $code = preg_replace('/\bfor\s+/', "for ", $code);
        $code = preg_replace('/\bwhile\s+/', "while ", $code);
        
        return $code;
    }
    
    private function addPythonLineBreaks($code) {
        // Разбиваем на токены, учитывая ключевые слова Python
        $keywords = implode('|', $this->keywords['python']);
        $pattern = '/(\b(?:' . $keywords . ')\b|:|\bclass\b|\bdef\b)/';
        
        $parts = preg_split($pattern, $code, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        $needNewline = false;
        
        for($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];
            
            if($part === ':') {
                $result .= ':';
                $needNewline = true;
            } elseif(in_array(trim($part), $this->keywords['python'])) {
                if($result && !preg_match('/\n$/', $result) && trim($part) !== 'else') {
                    $result .= "\n";
                }
                $result .= trim($part);
                if(trim($part) !== 'else') {
                    $result .= ' ';
                }
            } elseif($needNewline && trim($part)) {
                $result .= "\n" . trim($part);
                $needNewline = false;
            } else {
                $result .= $part;
            }
        }
        
        return $result;
    }
    
    private function addPythonIndentation($code) {
        $lines = explode("\n", $code);
        $formatted = [];
        $indentLevel = 0;
        
        foreach($lines as $line) {
            $trimmed = trim($line);
            if($trimmed === '') continue;
            
            // Add indentation
            $indent = str_repeat(' ', $indentLevel * $this->indentSize);
            $formatted[] = $indent . $trimmed;
            
            // Увеличиваем отступ после двоеточия
            if(preg_match('/:$/', $trimmed)) {
                $indentLevel++;
            }
            
            // Уменьшаем отступ для определенных ключевых слов на том же уровне
            if(preg_match('/^(else:|elif\s|except\s|finally:)/', $trimmed) && $indentLevel > 0) {
                // Корректируем отступ для текущей строки
                $formatted[count($formatted) - 1] = str_repeat(' ', max(0, ($indentLevel - 1) * $this->indentSize)) . $trimmed;
            }
        }
        
        return implode("\n", $formatted);
    }
    
    private function formatPowerShell($code) {
        // Нормализуем пробелы
        $code = $this->normalizeWhitespace($code);
        
        // Обрабатываем PowerShell структуры
        $code = $this->processPowerShellStructures($code);
        
        // Add line breaks
        $code = $this->addPowerShellLineBreaks($code);
        
        // Apply indentation
        return $this->addPowerShellIndentation($code);
    }
    
    private function processPowerShellStructures($code) {
        // Обрабатываем командлеты
        $code = preg_replace('/\b([A-Z][a-z]+-[A-Z][a-zA-Z]*)\b/', "$1", $code);
        
        // Handle functions
        $code = preg_replace('/\bfunction\s+([a-zA-Z_][a-zA-Z0-9_-]*)\s*\{/', "function $1 {", $code);
        
        // Обрабатываем условия и циклы
        $code = preg_replace('/\bif\s*\(/', "if(", $code);
        $code = preg_replace('/\bwhile\s*\(/', "while(", $code);
        $code = preg_replace('/\bfor\s*\(/', "for(", $code);
        $code = preg_replace('/\bforeach\s*\(/', "foreach(", $code);
        
        // Обрабатываем пайплайны
        $code = preg_replace('/\s*\|\s*/', " | ", $code);
        
        return $code;
    }
    
    private function addPowerShellLineBreaks($code) {
        // Добавляем переносы для основных конструкций
        $code = str_replace('{', "{\n", $code);
        $code = str_replace('}', "\n}", $code);
        
        // Добавляем переносы для пайплайнов
        $code = preg_replace('/\s*\|\s*/', "\n| ", $code);
        
        // Обрабатываем else
        $code = preg_replace('/\n\s*}\s*else\s*\{\n/', "\n} else {\n", $code);
        
        return $code;
    }
    
    private function addPowerShellIndentation($code) {
        $lines = explode("\n", $code);
        $formatted = [];
        $indentLevel = 0;
        
        foreach($lines as $line) {
            $trimmed = trim($line);
            if($trimmed === '') continue;
            
            // Уменьшаем отступ перед закрывающими скобками
            if(preg_match('/^}/', $trimmed)) {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            // Add indentation
            $indent = str_repeat(' ', $indentLevel * $this->indentSize);
            $formatted[] = $indent . $trimmed;
            
            // Увеличиваем отступ после открывающих скобок
            if(preg_match('/\{$/', $trimmed)) {
                $indentLevel++;
            }
        }
        
        return implode("\n", $formatted);
    }
    
    private function formatGeneric($code) {
        $code = $this->normalizeWhitespace($code);
        $code = str_replace('{', "{\n", $code);
        $code = str_replace('}', "\n}", $code);
        $code = str_replace(';', ";\n", $code);
        
        return $this->addGenericIndentation($code);
    }
    
    private function addGenericIndentation($code) {
        $lines = explode("\n", $code);
        $formatted = [];
        $indentLevel = 0;
        
        foreach($lines as $line) {
            $trimmed = trim($line);
            if($trimmed === '') continue;
            
            if(preg_match('/^}/', $trimmed)) {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            $indent = str_repeat(' ', $indentLevel * $this->indentSize);
            $formatted[] = $indent . $trimmed;
            
            if(preg_match('/\{$/', $trimmed)) {
                $indentLevel++;
            }
        }
        
        return implode("\n", $formatted);
    }
    
    private function normalizeWhitespace($code) {
        // Заменяем множественные пробелы одинарными, но сохраняем переносы строк
        $code = preg_replace('/[ \t]+/', ' ', $code);
        // Удаляем пробелы в начале и конце строк
        $code = preg_replace('/^ +| +$/m', '', $code);
        // Удаляем множественные переносы строк
        $code = preg_replace('/\n+/', "\n", $code);
        
        return trim($code);
    }
}
function formatCode($code, $language) {
    $formatter = new AdvancedCodeFormatter();
    return $formatter->formatCode($code, $language);
}

// Если файл запущен напрямую - возвращаем JSON API
if (isset($_POST['action']) && $_POST['action'] === 'format') {
    header('Content-Type: application/json');
    
    $code = $_POST['code'] ?? '';
    $language = $_POST['language'] ?? '';
    
    if (empty($code) || empty($language)) {
        echo json_encode(['error' => 'Code and language are required']);
        exit;
    }
    
    try {
        $formatted = formatCode($code, $language);
        echo json_encode(['success' => true, 'formatted_code' => $formatted]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
