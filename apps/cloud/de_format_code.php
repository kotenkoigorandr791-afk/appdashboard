<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
/**
 * Упрощенный компактор кода - удаляет пустые строки и сжимает код
 */
class AdvancedCodeCompactor {
    
    public function compactCode($code, $language) {
        // Удаляем все пустые строки для всех языков
        $code = $this->removeEmptyLines($code);
        
        switch(strtolower($language)) {
            case 'php':
                return $this->compactPHP($code);
            case 'html':
                return $this->compactHTML($code);
            case 'c':
            case 'c++':
            case 'cpp':
            case 'java':
            case 'javascript':
            case 'js':
                return $this->compactBraceLanguage($code);
            case 'python':
                return $this->compactPython($code);
            case 'powershell':
                return $this->compactPowerShell($code);
            default:
                return $this->compactGeneric($code);
        }
    }

    private function removeEmptyLines($code) {
        // Удаляем только полностью пустые строки (сохраняем пробелы в начале для Python)
        $lines = explode("\n", $code);
        $result = [];
        
        foreach ($lines as $line) {
            // Сохраняем строку если она не пустая (может содержать пробелы/табы для отступов)
            if (trim($line) !== '') {
                $result[] = $line;
            }
        }
        
        return implode("\n", $result);
    }

    private function compactPHP($code) {
        return $this->compactBraceLanguage($code);
    }

    private function compactBraceLanguage($code) {
        // Сохраняем директивы #include на отдельных строках
        $lines = explode("\n", $code);
        $processedLines = [];
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^#include/', $trimmed)) {
                $processedLines[] = $trimmed;
            } else {
                // Для остальных строк объединяем в одну строку для дальнейшей обработки
                if (empty($processedLines) || !preg_match('/^#include/', end($processedLines))) {
                    $processedLines[] = $trimmed;
                } else {
                    $processedLines[] = $trimmed;
                }
            }
        }
        
        // Объединяем обратно в строку, но сохраняем #include на отдельных строках
        $code = implode("\n", $processedLines);
        
        // Обрабатываем открывающие скобки - переносим на новую строку
        $code = preg_replace('/([^ ])\s*{\s*/', "$1\n{", $code);
        
        // Обрабатываем закрывающие скобки - переносим на новую строку
        $code = preg_replace('/\s*}\s*/', "\n}", $code);
        
        // Разбиваем на строки и обрабатываем
        $lines = explode("\n", $code);
        $result = [];
        $indentLevel = 0;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            
            // Сохраняем директивы #include без отступов
            if (preg_match('/^#include/', $trimmed)) {
                $result[] = $trimmed;
                continue;
            }
            
            // Уменьшаем отступ если это закрывающая скобка
            if ($trimmed === '}') {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            // Добавляем строку с правильным отступом
            $result[] = str_repeat('    ', $indentLevel) . $trimmed;
            
            // Увеличиваем отступ если это открывающая скобка
            if ($trimmed === '{') {
                $indentLevel++;
            }
        }
        
        return implode("\n", $result);
    }

    private function compactHTML($code) {
        // Агрессивное удаление пробелов для HTML
        $code = preg_replace('/\s+/', ' ', $code);
        $code = preg_replace('/>\s+</', '><', $code);
        $code = preg_replace('/\s+>/', '>', $code);
        $code = preg_replace('/<\s+/', '<', $code);
        
        return trim($code);
    }

    private function compactPython($code) {
        // Для Python только удаляем пустые строки, сохраняя все отступы
        return $code;
    }

    private function compactPowerShell($code) {
        return $this->compactBraceLanguage($code);
    }

    private function compactGeneric($code) {
        // Универсальное сжатие - только удаление пустых строк
        return $code;
    }
}

function compactCode($code, $language) {
    $compactor = new AdvancedCodeCompactor();
    return $compactor->compactCode($code, $language);
}

// Если файл запущен напрямую - возвращаем JSON API
if (isset($_POST['action']) && $_POST['action'] === 'compact') {
    header('Content-Type: application/json');
    
    $code = $_POST['code'] ?? '';
    $language = $_POST['language'] ?? '';
    
    if (empty($code) || empty($language)) {
        echo json_encode(['error' => 'Code and language are required']);
        exit;
    }
    
    try {
        $compacted = compactCode($code, $language);
        echo json_encode(['success' => true, 'compacted_code' => $compacted]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
