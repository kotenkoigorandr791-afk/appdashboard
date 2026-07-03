<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
/**
 * Удаление комментариев из кода (по языкам программирования) - Улучшенная версия
 */
class CommentRemover {
    
    public function removeComments($code, $language) {
        $language = strtolower($language);
        
        switch ($language) {
            case 'php':
                return $this->removePHPComments($code);
            case 'javascript':
            case 'js':
            case 'c':
            case 'c++':
            case 'cpp':
            case 'java':
            case 'csharp':
                return $this->removeCStyleComments($code);
            case 'css':
                return $this->removeCSSComments($code);
            case 'python':
            case 'py':
                return $this->removePythonComments($code);
            case 'html':
                return $this->removeHTMLComments($code);
            case 'sql':
                return $this->removeSQLComments($code);
            case 'bash':
            case 'sh':
            case 'shell':
                return $this->removeBashComments($code);
            case 'ruby':
            case 'rb':
                return $this->removeRubyComments($code);
            case 'powershell':
                return $this->removePowerShellComments($code);
            case 'matlab':
            case 'm':
                return $this->removeMatlabComments($code); // Добавлен MATLAB
            default:
                return $this->removeGenericComments($code);
        }
    }

    private function removeMatlabComments($code) {
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['%'], // Обычные однострочные комментарии
            'multi_line' => ['%{', '%}'], // Блочные комментарии MATLAB
            'matlab_special' => true // Флаг для специальной обработки MATLAB
        ]);
    }

    private function removePHPComments($code) {
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['//', '#'],
            'multi_line' => ['/*', '*/'],
            'html_comments' => true
        ]);
    }

    private function removeCStyleComments($code) {
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['//'],
            'multi_line' => ['/*', '*/']
        ]);
    }

    private function removeCSSComments($code) {
        return $this->removeCommentsWithStringAwareness($code, [
            'multi_line' => ['/*', '*/']
        ]);
    }

    private function removePythonComments($code) {
        // Сначала удаляем многострочные комментарии (тройные кавычки вне строк)
        $code = $this->removePythonMultilineComments($code);
        
        // Затем удаляем однострочные комментарии
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['#']
        ]);
    }

    private function removePythonMultilineComments($code) {
        $lines = explode("\n", $code);
        $result = [];
        $inMultilineComment = false;
        $inString = false;
        $stringChar = '';
        
        foreach ($lines as $line) {
            $cleanLine = '';
            $i = 0;
            $len = strlen($line);
            
            while ($i < $len) {
                if (!$inString && !$inMultilineComment) {
                    // Проверяем начало многострочного комментария
                    if (substr($line, $i, 3) === '"""' || substr($line, $i, 3) === "'''") {
                        $quoteType = substr($line, $i, 3);
                        
                        // Проверяем, это комментарий или строка?
                        // Если в начале строки или после пробелов - скорее всего комментарий
                        $before = substr($line, 0, $i);
                        if (trim($before) === '' && !$this->isInsidePythonString($line, $i)) {
                            $inMultilineComment = true;
                            $i += 3;
                            continue;
                        } else {
                            // Это строка - пропускаем
                            $cleanLine .= $quoteType;
                            $i += 3;
                            $inString = !$inString;
                            $stringChar = $quoteType;
                            continue;
                        }
                    }
                }
                
                if ($inMultilineComment) {
                    // Проверяем конец многострочного комментария
                    if (substr($line, $i, 3) === '"""' || substr($line, $i, 3) === "'''") {
                        $inMultilineComment = false;
                        $i += 3;
                        continue;
                    }
                    $i++;
                    continue;
                }
                
                // Обработка строк
                if (!$inString && ($line[$i] === '"' || $line[$i] === "'")) {
                    $inString = true;
                    $stringChar = $line[$i];
                    $cleanLine .= $line[$i];
                } elseif ($inString && $line[$i] === $stringChar) {
                    // Проверяем экранирование
                    if ($i > 0 && $line[$i-1] === '\\') {
                        $cleanLine .= $line[$i];
                    } else {
                        $inString = false;
                        $cleanLine .= $line[$i];
                    }
                } else {
                    $cleanLine .= $line[$i];
                }
                
                $i++;
            }
            
            // Если мы все еще в многострочном комментарии, не добавляем строку
            if (!$inMultilineComment) {
                $result[] = $cleanLine;
            } else {
                // Если это продолжение комментария, добавляем пустую строку для сохранения нумерации
                $result[] = '';
            }
        }
        
        return implode("\n", $result);
    }

    private function isInsidePythonString($line, $pos) {
        // Упрощенная проверка - в реальности нужен более сложный анализ
        $before = substr($line, 0, $pos);
        $quoteCount = substr_count($before, '"') + substr_count($before, "'");
        return $quoteCount % 2 !== 0;
    }

    private function removeHTMLComments($code) {
        return preg_replace('/<!--.*?-->/s', '', $code);
    }

    private function removeSQLComments($code) {
        $code = $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['--']
        ]);
        return $this->removeCommentsWithStringAwareness($code, [
            'multi_line' => ['/*', '*/']
        ]);
    }

    private function removeBashComments($code) {
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['#']
        ]);
    }

    private function removeRubyComments($code) {
        // Удаляем =begin ... =end блоки
        $code = preg_replace('/^=begin\s*.*?^=end\s*$/ms', '', $code);
        
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['#']
        ]);
    }

    private function removePowerShellComments($code) {
        $code = $this->removeCommentsWithStringAwareness($code, [
            'multi_line' => ['<#', '#>']
        ]);
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['#']
        ]);
    }

    private function removeGenericComments($code) {
        $code = $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['//', '#']
        ]);
        return $this->removeCommentsWithStringAwareness($code, [
            'multi_line' => ['/*', '*/']
        ]);
    }

    /**
     * Универсальная функция удаления комментариев с учетом строковых литералов
     * и специальная обработка для MATLAB разделителей %%
     */
    private function removeCommentsWithStringAwareness($code, $options) {
        $lines = explode("\n", $code);
        $result = [];
        
        $inMultiLineComment = false;
        $multiLineStart = $options['multi_line'][0] ?? null;
        $multiLineEnd = $options['multi_line'][1] ?? null;
        $singleLineMarkers = $options['single_line'] ?? [];
        $processHtml = $options['html_comments'] ?? false;
        $matlabSpecial = $options['matlab_special'] ?? false;
        
        foreach ($lines as $line) {
            $cleanLine = '';
            $i = 0;
            $len = strlen($line);
            $inString = false;
            $stringChar = '';
            $escaped = false;
            
            // Специальная проверка для MATLAB разделителей в начале строки
            if ($matlabSpecial && !$inMultiLineComment && !$inString) {
                $trimmedLine = ltrim($line);
                if (strlen($trimmedLine) >= 3 && substr($trimmedLine, 0, 2) === '%%' && $trimmedLine[2] === ' ') {
                    // Это разделитель %% с пробелом - сохраняем всю строку как есть
                    $result[] = $line;
                    continue;
                }
            }
            
            while ($i < $len) {
                $char = $line[$i];
                
                // Обработка экранирования
                if ($inString && $char === '\\' && !$escaped) {
                    $escaped = true;
                    $cleanLine .= $char;
                    $i++;
                    continue;
                }
                
                if (!$inString && !$inMultiLineComment) {
                    // Проверка начала многострочного комментария
                    if ($multiLineStart && substr($line, $i, strlen($multiLineStart)) === $multiLineStart) {
                        $inMultiLineComment = true;
                        $i += strlen($multiLineStart);
                        continue;
                    }
                    
                    // Проверка однострочных комментариев
                    $foundSingleLine = false;
                    foreach ($singleLineMarkers as $marker) {
                        if (substr($line, $i, strlen($marker)) === $marker) {
                            // Для MATLAB: проверяем, не является ли это разделителем %%
                            if ($matlabSpecial && $marker === '%') {
                                // Проверяем, не начинается ли с %% и пробела
                                if ($i < $len - 2 && $line[$i+1] === '%' && $line[$i+2] === ' ') {
                                    // Это разделитель - сохраняем
                                    $cleanLine .= '%% ';
                                    $i += 3;
                                    // Продолжаем обработку остальной части строки
                                    continue;
                                }
                            }
                            // Пропускаем остаток строки
                            $i = $len;
                            $foundSingleLine = true;
                            break;
                        }
                    }
                    if ($foundSingleLine) continue;
                    
                    // Проверка HTML комментариев
                    if ($processHtml && substr($line, $i, 4) === '<!--') {
                        // Для HTML комментариев нужно специальная обработка
                        $i += 4;
                        $inMultiLineComment = true;
                        $multiLineStart = '<!--';
                        $multiLineEnd = '-->';
                        continue;
                    }
                }
                
                if ($inMultiLineComment) {
                    // Проверка конца многострочного комментария
                    if ($multiLineEnd && substr($line, $i, strlen($multiLineEnd)) === $multiLineEnd) {
                        $inMultiLineComment = false;
                        $i += strlen($multiLineEnd);
                        
                        // Сбрасываем для HTML комментариев
                        if ($processHtml && $multiLineEnd === '-->') {
                            $multiLineStart = $options['multi_line'][0] ?? null;
                            $multiLineEnd = $options['multi_line'][1] ?? null;
                        }
                        continue;
                    }
                    $i++;
                    continue;
                }
                
                // Обработка строковых литералов
                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString = true;
                    $stringChar = $char;
                    $cleanLine .= $char;
                } elseif ($inString && $char === $stringChar && !$escaped) {
                    $inString = false;
                    $cleanLine .= $char;
                } else {
                    $cleanLine .= $char;
                }
                
                $escaped = false;
                $i++;
            }
            
            $result[] = $cleanLine;
        }
        
        return implode("\n", $result);
    }
}

function removeComments($code, $language) {
    $remover = new CommentRemover();
    return $remover->removeComments($code, $language);
}

// API режим
if (isset($_POST['action']) && $_POST['action'] === 'remove_comments') {
    header('Content-Type: application/json');
    
    $code = $_POST['code'] ?? '';
    $language = $_POST['language'] ?? 'auto';
    
    if (empty($code)) {
        echo json_encode(['error' => 'Code is required']);
        exit;
    }
    
    try {
        $cleaned = removeComments($code, $language);
        echo json_encode(['success' => true, 'cleaned_code' => $cleaned]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
