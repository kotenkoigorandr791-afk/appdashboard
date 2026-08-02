<?php
$__d=__DIR__;for($__n=0;$__n<16;$__n++){if(is_file($__d.'/smart_log_boot.php')){require_once $__d.'/smart_log_boot.php';break;}$__p=dirname($__d);if($__p===$__d)break;$__d=$__p;}
/**
  * Simplified code compactor - removes empty lines and compresses code
 */
class AdvancedCodeCompactor {
    
    public function compactCode($code, $language) {
        // Remove all empty lines for all languages
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
        // Remove only completely empty lines (preserve leading spaces for Python)
        $lines = explode("\n", $code);
        $result = [];
        
        foreach ($lines as $line) {
            // Keep line if not empty (may contain spaces/tabs for indentation)
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
        // Keep #include directives on separate lines
        $lines = explode("\n", $code);
        $processedLines = [];
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^#include/', $trimmed)) {
                $processedLines[] = $trimmed;
            } else {
                // For other lines, combine into one for further processing
                if (empty($processedLines) || !preg_match('/^#include/', end($processedLines))) {
                    $processedLines[] = $trimmed;
                } else {
                    $processedLines[] = $trimmed;
                }
            }
        }
        
        // Join back into string, but keep #include on separate lines
        $code = implode("\n", $processedLines);
        
        // Process opening braces - move to new line
        $code = preg_replace('/([^ ])\s*{\s*/', "$1\n{", $code);
        
        // Process closing braces - move to new line
        $code = preg_replace('/\s*}\s*/', "\n}", $code);
        
        // Split into lines and process
        $lines = explode("\n", $code);
        $result = [];
        $indentLevel = 0;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            
            // Keep #include directives without indentation
            if (preg_match('/^#include/', $trimmed)) {
                $result[] = $trimmed;
                continue;
            }
            
            // Decrease indent if closing brace
            if ($trimmed === '}') {
                $indentLevel = max(0, $indentLevel - 1);
            }
            
            // Add line with proper indentation
            $result[] = str_repeat('    ', $indentLevel) . $trimmed;
            
            // Increase indent if opening brace
            if ($trimmed === '{') {
                $indentLevel++;
            }
        }
        
        return implode("\n", $result);
    }

    private function compactHTML($code) {
        // Aggressive whitespace removal for HTML
        $code = preg_replace('/\s+/', ' ', $code);
        $code = preg_replace('/>\s+</', '><', $code);
        $code = preg_replace('/\s+>/', '>', $code);
        $code = preg_replace('/<\s+/', '<', $code);
        
        return trim($code);
    }

    private function compactPython($code) {
        // For Python only remove empty lines, preserving all indentation
        return $code;
    }

    private function compactPowerShell($code) {
        return $this->compactBraceLanguage($code);
    }

    private function compactGeneric($code) {
        // Universal compression - only remove empty lines
        return $code;
    }
}

function compactCode($code, $language) {
    $compactor = new AdvancedCodeCompactor();
    return $compactor->compactCode($code, $language);
}

// If file run directly - return JSON API
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
