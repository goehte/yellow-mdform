<?php
// MDForm - Markdown Form Extension for Datenstrom Yellow 
// https://github.com/goehte/yellow-mdform/
// This extension allows creating HTML forms from markdown-formatted files.
// NOTE: All v0.0.x are Alpha Versions - Revision Date 28.04.2026

class YellowMdform {
    // Extension version number
    const VERSION = "0.0.6";
    
    // Reference to Yellow CMS API instance
    public $yellow;

    // Called when the extension loads to set up defaults
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        
        // Directory for storing form definition files
        $this->yellow->system->setDefault("MDFormDirectory", "media/forms/");
        // Directory for storing CSV output files
        $this->yellow->system->setDefault("MDFormDirectoryCSVOutput", "media/tables/");
        // Directory for rate limiting files
        $this->yellow->system->setDefault("MDFormRateLimitDirectory", "cache/mdform/ratelimit/");
        
        // Passkey for CSRF hash generation
        $saltPasskey = $this->yellow->system->get("coreSitename");
        $this->yellow->system->setDefault("MDFormHashSaltPasskey", $saltPasskey);
        
        // Default email and restriction settings
        $this->yellow->system->setDefault("MDFormEmail", "noreply@server.com");
        $this->yellow->system->setDefault("MDFormEmailRestriction", "0");
        $this->yellow->system->setDefault("MDFormLinkRestriction", "1");
        $this->yellow->system->setDefault("MDFormAllowedExtensions", "mdf, fmd, md, form");  
        $this->yellow->system->setDefault("MDFormStyleSheet", "mdform.css");
        
        // Language translations for UI messages
        $this->yellow->language->setDefaults(array(
            "Language: en",
            "MDFormSubmitBtn: Submit",
            "MDFormMandatory: *",
            "MDFormSubmitted: <strong>Form successfully submitted</strong>",
            "MDFormCSVSaved: Success! Data saved.",
            "MDFormEmailSend: Success! Data send.",
            "MDFormMailHeader: Mail Header",
            "MDFormMailFooter: Mail Footer",
            "Language: de",
            "MDFormSubmitBtn: Senden",
            "MDFormMandatory: *",
            "MDFormSubmitted: <strong>Formular erfolgreich abgesendet.</strong>",
            "MDFormCSVSaved: Daten erfolgreich gespeichert.",
            "MDFormEmailSent: Daten erfolgreich gesendet.",
            "MDFormMailHeader: Mail Header",
            "MDFormMailFooter: Mail Footer",
        ));
    }

    // Main entry point for form element parsing
    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        
        // Only process elements named "mdform"
        if ($name == "mdform" && ($type == "block" || $type == "inline")) {
            list($file, $dispatchFormat) = $this->yellow->toolbox->getTextArguments($text); 
            $path = $this->yellow->system->get("MDFormDirectory");
            
            // Strip all path components to prevent directory traversal
            $file = basename(trim($file));
            
            // Verify the resolved path is within the allowed base directory
            $fullPath = realpath($path . $file);
            $basePath = realpath($path);
            
            // Validate path exists and is within base directory
            if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
                $output = "<p><em>[mdform] Error: Form file not found or access denied.</em></p>\n";
                return $output;
            }
            
            // Process form if filename provided
            if (!empty($file)) {
                // Check if the form file exists on disk
                if (file_exists($fullPath)) {
                    // Check if this is a form submission request
                    if (($page->getRequest("form-status") === "send") && ($page->getRequest("mdform-file") === $file)) {
                        $output .= $this->processSend($path, $file, $dispatchFormat, $page->getRequest("mdform-hash")); 
                    } 
                    // Render the form for standard display
                    else {
                        $output = $this->getForm($path, $file);                      
                    }
                } 
                // Handle case where file is missing
                else {
                    $output = "<p><em>[mdform] Error: File not found.</em></p>\n";
                }
            }
        }
        return $output;
    }

    // Load custom style sheet CSS into the page header
    public function onParsePageExtra($page, $name) {
        $output = null;
        // Check if header extra is being parsed
        if ($name == "header") {
            $assetLocation = $this->yellow->system->get("coreServerBase") . $this->yellow->system->get("coreAssetLocation");
            $style = $this->yellow->system->get("MDFormStyleSheet");
            // Append link tag if stylesheet is defined
            if ($style != "") {
                $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$assetLocation}{$style}\" />\n";
            }
        }
        return $output;
    }

    // Loads and validates form definition files
    private function getForm($filePath, $fileName) {
        $fileType = $this->yellow->toolbox->getFileType($filePath . $fileName);
        $allowed = $this->yellow->system->get("MDFormAllowedExtensions");
        $allowed = array_map('trim', explode(',', $allowed));

        // Only process allowed file extensions
        if (is_array($allowed) && in_array($fileType, $allowed)) {
            $formData = $this->readMarkdown(file_get_contents($filePath . $fileName));
            return $this->generateHTMLForm($formData, $fileName);
        }
        return false;
    }

    // Parses markdown-formatted form definition into a structured array
    private function readMarkdown($fileContent) {
        $lines = explode("\n", $fileContent);
        $formData = [];
        $counters = ['radio' => 0, 'check' => 0, 'toggle' => 0, 'input' => 0];

        // Process markdown line by line
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            $isMandatory = false;
            $cleanLine = $line;

            // Only strip trailing '*' if line contains form brackets
            if (strpos($line, '[') !== false && strpos($line, ']') !== false) {
                // Check if last character is mandatory marker
                if (substr($line, -1) === '*') {
                    $isMandatory = true;
                    $cleanLine = rtrim($line, '*');
                }
            }

            $labelPrefix = ""; 
            $elementBody = $cleanLine;
            
            // Extract label and body for form fields
            if (preg_match('/^(.+?):\s*(\[.*\].*)$/', $cleanLine, $matches)) {
                $labelPrefix = trim($matches[1]);
                $elementBody = trim($matches[2]);
            }

            $entry = ['label' => $labelPrefix, 'required' => $isMandatory, 'name' => '', 'type' => '', 'options' => [], 'placeholder' => '', 'autocomplete' => '', 'min' => '', 'max' => ''];

            // Extract autocomplete attributes if present
            if (preg_match('/^(.*)\{([a-z0-9\-]+)\}\s*$/i', $elementBody, $attrMatches)) {
                $elementBody = trim($attrMatches[1]);
                $entry['autocomplete'] = strtolower(trim($attrMatches[2]));
            }

            // Identify select dropdown elements
            if (preg_match('/^\[(.*?)\s*▼\s*;\s*(.*)\]$/u', $elementBody, $matches)) {
                $entry['type'] = 'select';
                $entry['name'] = $this->cleanName($labelPrefix ?: "dropdown_" . (++$counters['input']));
                $entry['placeholder'] = trim($matches[1]);
                $entry['options'] = array_map('trim', explode(',', $matches[2]));
            } 
            // Identify radio button groups
            elseif (preg_match('/^\[(\(\s*[xX ]?\s*\).*?)\]$/', $elementBody, $matches)) {
                $entry['type'] = 'radio';
                $entry['name'] = $this->cleanName($labelPrefix ?: "radio_group_" . (++$counters['radio']));
                $innerContent = $matches[1];
                $parts = preg_split('/,\s*(?=\()/', $innerContent);

                // Parse each radio option
                foreach ($parts as $p) {
                    // Match the (x) or ( ) part and label
                    if (preg_match('/\(\s*([xX ]?)\s*\)(.*)/', trim($p), $optionMatches)) {
                        $entry['options'][] = ['value' => trim($optionMatches[2]), 'checked' => (strtolower(trim($optionMatches[1])) === 'x')];
                    }
                }
            } 
            // Identify checkbox groups
            elseif (preg_match('/^\[(\[\s*[xX ]?\s*\].*?)\]$/', $elementBody, $matches)) {
                $entry['type'] = 'checkbox';
                $entry['name'] = $this->cleanName($labelPrefix ?: "check_group_" . (++$counters['check']));
                $innerContent = $matches[1];
                $parts = preg_split('/,\s*(?=\[)/', $innerContent);

                // Parse each checkbox option
                foreach ($parts as $p) {
                    // Match the [x] or [ ] part and label
                    if (preg_match('/\[\s*([xX ]?)\s*\](.*)/', trim($p), $optionMatches)) {
                        $entry['options'][] = ['value' => trim($optionMatches[2]), 'checked' => (strtolower(trim($optionMatches[1])) === 'x')];
                    }
                }
            }
            // Identify toggle inputs
            elseif (preg_match('/^\[(?:(.*?):\s*)?ON\/OFF\]$/i', $elementBody, $matches)) {
                $entry['type'] = 'toggle';
                $toggleName = isset($matches[1]) ? trim($matches[1]) : "";
                $entry['name'] = $this->cleanName($toggleName ?: $labelPrefix ?: "toggle_" . (++$counters['toggle']));
            }
            // Identify date inputs
            elseif (preg_match('/^\[DD\/MM\/YYYY(?:;(\d{4}-\d{2}-\d{2}|TODAY)\.\.(\d{4}-\d{2}-\d{2}|TODAY))?\]$/', $elementBody, $dateMatches)) {
                $entry['type'] = 'date';
                $dateMin = isset($dateMatches[1]) ? $dateMatches[1] : null;
                $dateMax = isset($dateMatches[2]) ? $dateMatches[2] : null;
                
                // Convert TODAY keyword to current date
                if ($dateMin === 'TODAY') {
                    $dateMin = date('Y-m-d');
                }
                // Convert TODAY keyword to current date
                if ($dateMax === 'TODAY') {
                    $dateMax = date('Y-m-d');
                }
                
                $entry['min'] = $dateMin;
                $entry['max'] = $dateMax;
            }       
            // Logic for Number/Text/Textarea detection
            elseif (preg_match('/^\[(.+)\]$/', $elementBody, $matches)) {
                $rawContent = trim($matches[1]);

                // Textarea check (prioritize to avoid misclassifying "..." as a range)
                if (strpos($rawContent, '...') !== false) {
                    $dotsCount = strlen($rawContent) - strlen(rtrim($rawContent, '.'));
                    $entry['type'] = 'textarea';
                    $entry['placeholder'] = trim($rawContent, '. ');
                    $entry['name'] = $this->cleanName($labelPrefix ?: $entry['placeholder'] ?: "textarea_" . (++$counters['input']));
                    $entry['min'] = ($dotsCount >= 3) ? $dotsCount : 2;
                }
                // Number input: Check for digits, ranges, or floats
                // Number input: [5], [5;1..10], [;1..10], [1..10], [5.0], [3.14;0..10]
                // Number input: [Placeholder], [Placeholder;Min..Max], [;Min..Max]
                elseif (preg_match('/^\[(?:(\d+(?:\.\d+)?))?(?:;(\d+(?:\.\d+)?)\.\.(\d+(?:\.\d+)?))?\]$/', $elementBody, $numberMatches)) {
                    $entry['type'] = 'number';
                    $entry['placeholder'] = !empty($numberMatches[1]) ? $numberMatches[1] : null;
                    $entry['min'] = $numberMatches[2] ?? null;
                    $entry['max'] = $numberMatches[3] ?? null;
                       
                    $step = "1";
                    // Calculate step based on decimal precision
                    if (preg_match('/\.\d+/', $elementBody)) {
                        $decimals = [];
                        // Check placeholder for decimals
                        if ($entry['placeholder'] !== null && strpos($entry['placeholder'], '.') !== false) {
                            $decimals[] = strlen(explode('.', $entry['placeholder'])[1]);
                        }
                        // Check min value for decimals
                        if ($entry['min'] !== null && strpos($entry['min'], '.') !== false) {
                            $decimals[] = strlen(explode('.', $entry['min'])[1]);
                        }
                        // Check max value for decimals
                        if ($entry['max'] !== null && strpos($entry['max'], '.') !== false) {
                            $decimals[] = strlen(explode('.', $entry['max'])[1]);
                        }
                        // Set step to required precision
                        if (!empty($decimals)) {
                            $maxDecimals = max($decimals);
                            $step = "0." . str_repeat("0", $maxDecimals - 1) . "1";
                        }
                    }
                    $entry['step'] = $step;
                    $entry['name'] = $this->cleanName($labelPrefix ?: "number_" . (++$counters['input']));
                }
                // Default to standard text field
                else {
                    $entry['type'] = 'text';
                    $entry['placeholder'] = trim($rawContent, '. ');
                    $entry['name'] = $this->cleanName($labelPrefix ?: $entry['placeholder'] ?: "text_" . (++$counters['input']));
                }
            }
            // Handle standalone markdown text lines
            elseif (empty($labelPrefix) && !preg_match('/^\[.*\]$/', $cleanLine)) {
                $entry['type'] = 'markdown';
                $entry['content'] = $cleanLine;
                $entry['name'] = 'markdown_' . count($formData);
            }

            // Add valid field definitions to the list
            if ($entry['type']) {
                $formData[] = $entry;
            }
        }
        return $formData;
    }

    // Converts parsed form structure into HTML markup
    private function generateHTMLForm($formData, $fileName) {
        $output = "<div class=\"mdform-container\">\n  <form method=\"post\">\n";
        $output .= "    <input type=\"hidden\" name=\"mdform-file\" value=\"" . htmlspecialchars($fileName) . "\">\n";

        // Loop through each field to generate HTML
        foreach ($formData as $field) {
            // Render non-input markdown elements
            if ($field['type'] === 'markdown') {
                $parsedContent = trim($this->parseText($this->yellow->page, $field['content']));
                $output .= "    <div class=\"mdform-markdown\">$parsedContent</div>\n";
                continue;
            }

            $req = $field['required'] ? "required" : "";
            $star = $field['required'] ? $this->yellow->language->getText("MDFormMandatory") : "";

            $output .= "    <p class=\"mdform-group\">\n";
            // Add label if it exists
            if ($field['label']) {
                $output .= "      <strong>{$field['label']}: $star</strong><br>\n";
            }

            $htmlType = $field['type'];
            // Apply specific autocomplete-based input types
            if ($field['autocomplete'] === 'email' && $field['type'] === 'text') {
                $htmlType = 'email';
            } 
            // Map tel type
            elseif ($field['autocomplete'] === 'tel' && $field['type'] === 'text') {
                $htmlType = 'tel';
            } 
            // Map url type
            elseif ($field['autocomplete'] === 'url' && $field['type'] === 'text') {
                $htmlType = 'url';
            } 
            // Map password type
            elseif ($field['autocomplete'] === 'password' && $field['type'] === 'text') {
                $htmlType = 'password';
            }

            // Generate field markup based on type
            switch ($field['type']) {
                // Render select dropdowns
                case 'select':
                    $output .= "      <select name=\"{$field['name']}\" class=\"form-control\" $req";
                    // Append autocomplete attribute
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= " style=\"width:100%\" >\n";
                    $output .= "        <option value=\"\">{$field['placeholder']}</option>\n";
                    // Add dropdown options
                    foreach ($field['options'] as $option) {
                        $output .= "        <option value=\"$option\">$option</option>\n";
                    }
                    $output .= "      </select>\n";
                    break;
                // Render radio buttons
                case 'radio':
                    // Loop through radio options
                    foreach ($field['options'] as $option) {
                        $output .= "      <label><input type=\"radio\" name=\"{$field['name']}\" class=\"form-control\" value=\"{$option['value']}\" $req";
                        // Add autocomplete
                        if ($field['autocomplete']) {
                            $output .= " autocomplete=\"{$field['autocomplete']}\"";
                        }
                        // Set checked state
                        if ($option['checked']) {
                            $output .= " checked";
                        }
                        $output .= "> {$option['value']}</label> \n";
                    }
                    break;   
                // Render checkboxes
                case 'checkbox':
                    // Loop through checkbox options
                    foreach ($field['options'] as $option) {
                        $output .= "      <label><input type=\"checkbox\" name=\"{$field['name']}[]\" class=\"form-control\" value=\"{$option['value']}\"";
                        // Add autocomplete
                        if ($field['autocomplete']) {
                            $output .= " autocomplete=\"{$field['autocomplete']}\"";
                        }
                        // Set checked state
                        if ($option['checked']) {
                            $output .= " checked";
                        }
                        $output .= "> {$option['value']}</label> \n";
                    }
                    break;
                // Render toggle switch
                case 'toggle':
                    $output .= "      <input type=\"checkbox\" name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control switch\" $req value=\"ON\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= ">\n";
                    $output .= "      <label class=\"switch\" for=\"{$field['name']}\">";
                    $toggleText = $field['name'];
                    // Format toggle text label
                    if (strpos($toggleText, "toggle_") !== 0) {                     
                        $toggleText = preg_replace('/_/', ' ', $toggleText);
                        $output .= "$toggleText $star\n";
                    }
                    $output .= "</label>\n";
                    break;
                // Render date input
                case 'date':
                    $output .= "      <input type=\"date\" name=\"{$field['name']}\" class=\"form-control\"";
                    // Add min date limit
                    if (isset($field['min']) && $field['min'] !== null) {
                        $output .= " min=\"" . htmlspecialchars($field['min']) . "\"";
                    }
                    // Add max date limit
                    if (isset($field['max']) && $field['max'] !== null) {
                        $output .= " max=\"" . htmlspecialchars($field['max']) . "\"";
                    }
                    $output .= " $req style=\"width:100%\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= ">\n";
                    break;
                // Render number input
                case 'number':
                    $output .= "      <input type=\"number\" name=\"{$field['name']}\" class=\"form-control\"";
                    // Add placeholder
                    if (!empty($field['placeholder'])) {
                        $output .= " placeholder=\"" . htmlspecialchars($field['placeholder']) . "\"";
                    }
                    // Add min number limit
                    if (isset($field['min']) && $field['min'] !== null) {
                        $output .= " min=\"" . htmlspecialchars($field['min']) . "\"";
                    }
                    // Add max number limit
                    if (isset($field['max']) && $field['max'] !== null) {
                        $output .= " max=\"" . htmlspecialchars($field['max']) . "\"";
                    }
                    // Add number step precision
                    if (isset($field['step']) && $field['step'] !== null) {
                        $output .= " step=\"" . htmlspecialchars($field['step']) . "\"";
                    }
                    $output .= " $req style=\"width:100%\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= ">\n";
                    break;
                // Render textarea
                case 'textarea':
                    $output .= "      <textarea name=\"{$field['name']}\" class=\"form-control\" placeholder=\"{$field['placeholder']}\" $req style=\"width:100%\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= " rows=\"{$field['min']}\"></textarea>\n";
                    break;
                // Render standard text inputs
                case 'text':
                    $output .= "      <input type=\"$htmlType\" name=\"{$field['name']}\" class=\"form-control\" placeholder=\"{$field['placeholder']}\" $req style=\"width:100%\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= ">\n";
                    break;
            }
            $output .= "    </p>\n";
        }

        $csrfToken = $this->createHashString($this->yellow->system->get("MDFormHashSaltPasskey"));
        $output .= "    <input type=\"hidden\" name=\"mdform-hash\" value=\"" . htmlspecialchars($csrfToken) . "\" />\n";
        $output .= "    <input type=\"hidden\" name=\"mdform-referer\" value=\"" . $this->yellow->toolbox->getServer("HTTP_REFERER") . "\" />\n";
        $output .= "    <input type=\"hidden\" name=\"form-status\" value=\"send\" />\n";        
        $output .= "    <p><button type=\"submit\" class=\"btn\">" . $this->yellow->language->getText("MDFormSubmitBtn") . "</button> </p>\n  </form>\n</div>\n";
        return $output;
    }
     
    // Parses markdown text using the system's default parser
    private function parseText($page, $text, $singleLine = true) {
        $parser = $this->yellow->extension->get($this->yellow->system->get("parser"));
        $output = $parser->onParseContentRaw($page, $text);
        // Clean up paragraph tags for inline display
        if ($singleLine) {
            // Check for surrounding paragraph tags
            if (substr($output, 0, 3) == "<p>" && substr($output, -5) == "</p>\n") {
                $output = substr($output, 3, -5);
            }
        }
        return $output;       
    }    
    
    // Extracts ONLY data-holding fields for backend processing
    private function getFormMetadata($fileContent) {
        $formData = $this->readMarkdown($fileContent);
        $formStructure = [];
        
        // Loop through fields to extract names and requirements
        foreach ($formData as $field) {
            // SKIP display-only markdown elements
            if (!($field['type'] === 'markdown')) {
                $formStructure[$field['name']] = [
                    'name' => $field['name'], 
                    'required' => $field['required'],
                    'type' => $field['type']
                ];
            }
        }
        return $formStructure;
    }

    // Handles form submission security and dispatch logic
    private function processSend($filePath, $fileName, $dispatchFormat, $hash) {
        $output = "<div class=\"mdform-container\">\n ";
        $output .= "<p>" . $this->yellow->language->getText("MDFormSubmitted") . "</p>\n ";
        
        $receivedHash = $this->yellow->page->getRequest("mdform-hash");
        // Validate CSRF token before processing
        if (!$this->checkHashString($receivedHash, $this->yellow->system->get("MDFormHashSaltPasskey"))) {
            return "<p><em>[mdform] Error: Security token invalid or expired. Please refresh.</em></p>";
        }

        // Verify if IP is being rate limited
        if ($this->isRateLimited()) {
            return "<p><em>[mdform] Error: Please wait a moment before submitting again.</em></p>";
        }
        
        // Execute dispatch methods if defined
        if (!is_string_empty($dispatchFormat)) {
            $formStructure = $this->getFormMetadata(file_get_contents($filePath . $fileName));
            $dispatchCommands = preg_split('/[\s,]+/', $dispatchFormat, -1, PREG_SPLIT_NO_EMPTY);

            // Execute each dispatch method in order
            foreach ($dispatchCommands as $cmd) {
                // Handle HTML display dispatch
                if ($cmd === "html") {
                    $output .= $this->subDispatchHtml($formStructure);
                } 
                // Handle CSV export dispatch
                elseif ($cmd === "csv") {
                    $output .= $this->subDispatchCsv($formStructure, $fileName);
                } 
                // Handle Email notification dispatch
                elseif ($cmd === "email") {
                    $output .= $this->subDispatchEmail($formStructure);
                }
            }
        }
        $output .= "</div>\n ";
        return $output;
    }

    // Cleans field names by replacing special characters with underscores
    private function cleanName($name) {
        return str_replace([' ', '.', '[', ']', ':', '*'], '_', trim($name));
    }

    // Creates a time-limited hash token for CSRF protection
    public function createHashString($salt) {
        $ip = $this->yellow->toolbox->getServer("REMOTE_ADDR");
        $hour = (int)(time() / 3600);
        $hash = $this->yellow->toolbox->createHash($salt . $ip . $hour, "sha256");
        // Handle potential hash failure
        if (is_string_empty($hash)) {
            $hash = "error-hash-algorithm-sha256";
        }
        return $hash;
    }

    // Verifies if the submitted CSRF token is valid
    public function checkHashString($hash, $salt) {
        $ip = $this->yellow->toolbox->getServer("REMOTE_ADDR");
        $currentHour = (int)(time() / 3600);
        $previousHour = $currentHour - 1;

        // Allow tokens from current or previous hour block
        return $this->yellow->toolbox->verifyHash($salt . $ip . $currentHour, "sha256", $hash) || 
               $this->yellow->toolbox->verifyHash($salt . $ip . $previousHour, "sha256", $hash);
    }
    
    // Limits form submissions per IP/Session to prevent spam
    private function isRateLimited() {
        $limitDir = $this->yellow->system->get("MDFormRateLimitDirectory");
        $ip = $this->yellow->toolbox->getServer("REMOTE_ADDR");
        $userAgent = $this->yellow->toolbox->getServer("HTTP_USER_AGENT") ?? '';
        $sessionId = session_id() ?: 'no-session';
        
        // Create unique visitor fingerprint
        $fingerprint = hash("sha256", $ip . '|' . $userAgent . '|' . $sessionId);
        $fingerprintLimitFile = $limitDir . $fingerprint;
        $ipLimitFile = $limitDir . "ip_" . hash("sha256", $ip);
    
        // Initialize limit storage directory
        if (!is_dir($limitDir)) {
            mkdir($limitDir, 0700, true);
        }
        
        $this->cleanupOldRateLimitFiles($limitDir);
        
        // Check session-based rate limit
        if (file_exists($fingerprintLimitFile)) {
            $lastSubmission = (int)file_get_contents($fingerprintLimitFile);
            $waitTime = 20; 
            // Deny if too soon
            if ((time() - $lastSubmission) < $waitTime) {
                return true;
            }
        }
        
        // Check hard IP-based rate limit
        if (file_exists($ipLimitFile)) {
            $lastIpSubmission = (int)file_get_contents($ipLimitFile);
            $waitTime = 10; 
            // Deny if too soon
            if ((time() - $lastIpSubmission) < $waitTime) {
                return true;
            }
        }
        
        // Store timestamp for current submission
        file_put_contents($fingerprintLimitFile, time(), LOCK_EX);
        file_put_contents($ipLimitFile, time(), LOCK_EX);
        return false;
    }

    // Removes expired rate limit tracking files
    private function cleanupOldRateLimitFiles($dir) {
        $maxAge = 3600; 
        
        // Check if directory exists
        if (!is_dir($dir)) {
            return;
        }
        
        // Iterate through tracking files
        foreach (glob($dir . '*') as $file) {
            // Delete files older than one hour
            if (is_file($file) && (time() - filemtime($file) > $maxAge)) {
                @unlink($file);
            }
        }
    }

    // Dispatch: Formats submitted data as HTML
    private function subDispatchHtml($formStructure) {
        $output = ""; 
        // Iterate through submission headers
        foreach (array_keys($formStructure) as $header) {
            $val = $this->yellow->page->getRequest($header);
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $output .= htmlspecialchars($header) . " => " . htmlspecialchars(trim($displayVal)) . "<br>\n";
        }
        return $output;
    }

    // Dispatch: Appends submitted data to a CSV file
    private function subDispatchCsv($formStructure, $fileName)  {
        $output = ""; 
        $delimiter = ",";
        $enclosure = '"';
        $escape = "\\";
        
        $csvPath = $this->yellow->system->get("MDFormDirectoryCSVOutput") . $fileName . ".csv"; 
        $expectedHeaders = array_keys($formStructure);

        $csvDir = dirname($csvPath);
        // Ensure CSV directory exists
        if (!is_dir($csvDir)) {
            mkdir($csvDir, 0755, true);
        }

        // Validate consistency of existing CSV files
        if (file_exists($csvPath)) {
            $handle = fopen($csvPath, 'r');
            $existingHeader = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
            fclose($handle);
            
            // Backup and restart if structure has changed
            if ($existingHeader !== $expectedHeaders) {
                rename($csvPath, $csvPath . "." . date("YmdHis") . ".bak");
            }
        }

        $dataRow = [];
        // Map submitted values to expected headers
        foreach ($expectedHeaders as $header) {
            $val = $this->yellow->page->getRequest($header);
            
            // Join array values with semicolons
            if (is_array($val)) {
                $val = implode("; ", $val); 
            }
            
            $dataRow[] = is_string($val) ? $val : (string)$val;
        }

        $isNew = !file_exists($csvPath);
        $handle = fopen($csvPath, 'a');
        
        // Handle file write failures
        if ($handle === false) {
            return "<p><em>[mdform] Error: Cannot open CSV file for writing.</em></p>";
        }

        // Write CSV header for new files
        if ($isNew) {
            fputcsv($handle, $expectedHeaders, $delimiter, $enclosure, $escape);
        }

        fputcsv($handle, $dataRow, $delimiter, $enclosure, $escape);
        fclose($handle);
        
        $output = "<p>" . $this->yellow->language->getText("MDFormCSVSaved") . "</p>\n";
        return $output;
    }

    // Dispatch: Sends submitted data via email
    private function subDispatchEmail($formStructure) {
        $output = "";
        $message = "";
        
        // Construct email body from submission
        foreach (array_keys($formStructure) as $header) {
            $val = $this->yellow->page->getRequest($header);
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $message .= htmlspecialchars($header) . " => " . htmlspecialchars(trim($displayVal)) . "\r\n";
        }
        
        $referer = trim($this->yellow->page->getRequest("mdform-referer"));
        $sitename = $this->yellow->system->get("sitename");
        $siteEmail = $this->yellow->system->get("MDFormEmail");
        $subject = $this->yellow->page->get("title") . " - " . $sitename;
        
        $userName = $this->yellow->system->get("author");
        $userEmail = $this->yellow->system->get("email");
        
        $headerText = $this->yellow->language->getText("MDFormMailHeader");
        $headerText = str_replace("\\n", "\r\n", $headerText);
        $footerText = $this->yellow->language->getText("MDFormMailFooter");
        $footerText = str_replace("\\n", "\r\n", $footerText);
        
        // Apply page-specific email overrides if allowed
        if ($this->yellow->page->isExisting("author") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $userName = $this->yellow->page->get("author");
        }
        // Apply page-specific email overrides if allowed
        if ($this->yellow->page->isExisting("email") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $userEmail = $this->yellow->page->get("email");
        }
        
        $userName = $this->sanitizeEmailName($userName);
        $userEmail = $this->sanitizeEmailAddress($userEmail);
        $sitename = $this->sanitizeEmailName($sitename);
        
        // Validate sender email address format
        if (is_string_empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $output = "<p><em>[mdform] Error: Email address settings not valid.</em></p>";
            return $output;
        }
        
        $referer = filter_var($referer, FILTER_SANITIZE_URL);
        
        // Construct email header array
        $mailHeaders = array(
            "To" => $this->yellow->lookup->normaliseAddress("$userName <$userEmail>"),
            "From" => $this->yellow->lookup->normaliseAddress("$sitename <$siteEmail>"),
            "Subject" => $this->sanitizeEmailSubject($subject),
            "Date" => date(DATE_RFC2822),
            "Mime-Version" => "1.0",
            "Content-Type" => "text/plain; charset=utf-8",
            "X-Referer-Url" => $referer,
            "X-Request-Url" => $this->yellow->page->getUrl()
        );
        
        $mailMessage = "$headerText\r\n\r\n$message\r\n-- \r\n$footerText";
        // Execute system mail function
        $output = $this->yellow->toolbox->mail("MDForm", $mailHeaders, $mailMessage) 
            ? ("<p>" . $this->yellow->language->getText("MDFormEmailSent") . "</p>\n")
            : "<p><em>[mdform] Error: Email not sent</em></p>";
        
        return $output;
    }

    // Cleans email name fields to prevent injection
    private function sanitizeEmailName($name) {
        $name = preg_replace('/[\r\n\x00]/', '', $name);
        $name = str_replace(['<', '>', ',', ';'], '', $name);
        return trim($name);
    }

    // Cleans email address fields to prevent injection
    private function sanitizeEmailAddress($email) {
        $email = preg_replace('/[\r\n\x00]/', '', $email);
        return trim($email);
    }

    // Cleans email subject lines to prevent buffer issues
    private function sanitizeEmailSubject($subject) {
        $subject = preg_replace('/[\r\n\x00]/', '', $subject);
        $subject = trim($subject);
        return mb_substr($subject, 0, 255, 'UTF-8');
    }
}
