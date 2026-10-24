<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
/**
  * Removing comments from code (by programming language) - Improved version
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
                return $this->removeMatlabComments($code); // MATLAB added
            default:
                return $this->removeGenericComments($code);
        }
    }

    private function removeMatlabComments($code) {
        return $this->removeCommentsWithStringAwareness($code, [
            'single_line' => ['%'], // Regular single-line comments
            'multi_line' => ['%{', '%}'], // MATLAB block comments
            'matlab_special' => true // Flag for special MATLAB handling
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
        // First remove multiline comments (triple quotes outside strings)
        $code = $this->removePythonMultilineComments($code);
        
        // Then remove single-line comments
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
                    // Check for start of multiline comment
                    if (substr($line, $i, 3) === '"""' || substr($line, $i, 3) === "'''") {
                        $quoteType = substr($line, $i, 3);
                        
                        // Check if this is a comment or string?
                        // If at start of line or after whitespace - likely a comment
                        $before = substr($line, 0, $i);
                        if (trim($before) === '' && !$this->isInsidePythonString($line, $i)) {
                            $inMultilineComment = true;
                            $i += 3;
                            continue;
                        } else {
                            // This is a string - skip
                            $cleanLine .= $quoteType;
                            $i += 3;
                            $inString = !$inString;
                            $stringChar = $quoteType;
                            continue;
                        }
                    }
                }
                
                if ($inMultilineComment) {
                    // Check for end of multiline comment
                    if (substr($line, $i, 3) === '"""' || substr($line, $i, 3) === "'''") {
                        $inMultilineComment = false;
                        $i += 3;
                        continue;
                    }
                    $i++;
                    continue;
                }
                
                // String handling
                if (!$inString && ($line[$i] === '"' || $line[$i] === "'")) {
                    $inString = true;
                    $stringChar = $line[$i];
                    $cleanLine .= $line[$i];
                } elseif ($inString && $line[$i] === $stringChar) {
                    // Check escaping
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
            
            // If still in multiline comment, don't add line
            if (!$inMultilineComment) {
                $result[] = $cleanLine;
            } else {
                // If this is a comment continuation, add empty line to preserve line numbers
                $result[] = '';
            }
        }
        
        return implode("\n", $result);
    }

    private function isInsidePythonString($line, $pos) {
        // Simplified check - in reality more complex analysis needed
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
        // Remove =begin ... =end blocks
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
      * Universal comment removal function accounting for string literals
      * and special handling for MATLAB %% separators
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
            
            // Special check for MATLAB section markers at start of line
            if ($matlabSpecial && !$inMultiLineComment && !$inString) {
                $trimmedLine = ltrim($line);
                if (strlen($trimmedLine) >= 3 && substr($trimmedLine, 0, 2) === '%%' && $trimmedLine[2] === ' ') {
                    // This is %% section separator with space - preserve entire line as-is
                    $result[] = $line;
                    continue;
                }
            }
            
            while ($i < $len) {
                $char = $line[$i];
                
                // Escape handling
                if ($inString && $char === '\\' && !$escaped) {
                    $escaped = true;
                    $cleanLine .= $char;
                    $i++;
                    continue;
                }
                
                if (!$inString && !$inMultiLineComment) {
                    // Check for start of multiline comment
                    if ($multiLineStart && substr($line, $i, strlen($multiLineStart)) === $multiLineStart) {
                        $inMultiLineComment = true;
                        $i += strlen($multiLineStart);
                        continue;
                    }
                    
                    // Check for single-line comments
                    $foundSingleLine = false;
                    foreach ($singleLineMarkers as $marker) {
                        if (substr($line, $i, strlen($marker)) === $marker) {
                            // For MATLAB: check if this is a %% section separator
                            if ($matlabSpecial && $marker === '%') {
                                // Check if it starts with %% and space
                                if ($i < $len - 2 && $line[$i+1] === '%' && $line[$i+2] === ' ') {
                                    // This is a section separator - preserve
                                    $cleanLine .= '%% ';
                                    $i += 3;
                                    // Continue processing rest of line
                                    continue;
                                }
                            }
                            // Skip rest of line
                            $i = $len;
                            $foundSingleLine = true;
                            break;
                        }
                    }
                    if ($foundSingleLine) continue;
                    
                    // Check for HTML comments
                    if ($processHtml && substr($line, $i, 4) === '<!--') {
                        // For HTML comments special handling is needed
                        $i += 4;
                        $inMultiLineComment = true;
                        $multiLineStart = '<!--';
                        $multiLineEnd = '-->';
                        continue;
                    }
                }
                
                if ($inMultiLineComment) {
                    // Check for end of multiline comment
                    if ($multiLineEnd && substr($line, $i, strlen($multiLineEnd)) === $multiLineEnd) {
                        $inMultiLineComment = false;
                        $i += strlen($multiLineEnd);
                        
                        // Reset for HTML comments
                        if ($processHtml && $multiLineEnd === '-->') {
                            $multiLineStart = $options['multi_line'][0] ?? null;
                            $multiLineEnd = $options['multi_line'][1] ?? null;
                        }
                        continue;
                    }
                    $i++;
                    continue;
                }
                
                // String handling
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

// API mode
if (isset($_POST['action']) && $_POST['action'] === 'remove_comments') {
    require_once dirname(__DIR__, 2) . '/lib/endpoint_guard.php';
    app_guard_session_user('remove_comments', 120);
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
