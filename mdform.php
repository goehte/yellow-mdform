<?php
// MDForm - Markdown Form Extension for Datenstrom Yellow 
// https://github.com/goehte/yellow-mdform/
// This extension allows creating HTML forms from markdown-formatted files.
// NOTE: All v0.0.x are Alpha Versions
// Alpha Revision v0.0.6.4 - Date 29.04.2026 - Bug-fix & Enhancement: Error messages translated and new toggle feature [OFF/ON] to have preselected toggle switch
// Alpha Revision v0.0.7.2 - Date 30.04.2026 - Bug-fix & Styling
// Alpha Revision v0.0.8.1 - Date 30.04.2026 - Added Cookie to avoid resubmits
// Alpha Revision v0.0.8.2 - Date 01.05.2026 - Mail Header on Page YAML added
// Alpha Revision v0.0.8.3 - Date 05.05.2026 - E-Mail sending updated
// Alpha Revision v0.0.9.1 - Date 06.05.2026 - Image CAPTCHA added, pre-fill HTML during resubmit added
// Alpha Revision v0.0.9.2 - Date 06.05.2026 - Hidden fields added, output added
// Alpha Revision v0.0.9.3 - Date 13.05.2026 - Special function {_cc_email} added
// Alpha Revision v0.0.9.4 - Date 13.05.2026 - Special function {validate_email} added

class YellowMdform {
    // Extension version number
    const VERSION = "0.0.9";
    
    // Reference to Yellow CMS API instance
    public $yellow;

    // Called when the extension loads to set up defaults
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        
        // Directory for storing form definition files
        $this->yellow->system->setDefault("MDFormDirectory", "media/forms/");
        // Directory for storing CSV output files
        $this->yellow->system->setDefault("MDFormDirectoryCSVOutput", "media/tables/forms/");
        // Directory for rate limiting files
        $this->yellow->system->setDefault("MDFormRateLimitDirectory", "cache/mdform/ratelimit/");
        
        // Passkey for CSRF hash generation
        $saltPasskey = $this->yellow->system->get("coreSitename");
        $this->yellow->system->setDefault("MDFormHashSaltPasskey", $saltPasskey);
        
        // Default email and restriction settings
        $this->yellow->system->setDefault("MDFormEmailRestriction", "0");
        $this->yellow->system->setDefault("MDFormLinkRestriction", "0");
        $this->yellow->system->setDefault("MDFormResubmitCookie", "0");
        $this->yellow->system->setDefault("MDFormAllowedExtensions", "mdf, md, form");  
        $this->yellow->system->setDefault("MDFormStyleSheet", "mdform.css");
        $this->yellow->system->setDefault("MDFormJavaScript", "mdform.js");
        $this->yellow->system->setDefault("MDFormActivateJavaScript", "1"); // JS needed only needed for live output: {output:name}.
        $this->yellow->system->setDefault("MDFormKeyValueSeperator", ": "); // How the key name and the value get shown in HTML and Email output.
        
        // Language translations for UI messages
        $this->yellow->language->setDefaults(array(
            "Language: en",
            "MDFormSubmitBtn: Submit",
            "MDFormMandatory: *",
            "MDFormSubmitted: <strong>  &#9989; Form successfully submitted.</strong>",
            "MDFormHTMLOutput: You have provided the following data:",
            "MDFormCSVSaved: Success! Data saved.",
            "MDFormEmailSend: Success! Data send.",
            "MDFormEmailCopySend: Copy of this from data send to: @sendermail.",
            "MDFormEmailValidateSend: Please validate your data, check your mailbox @sendermail and click the validation link.",
            "MDFormValidateSuccess: <strong>  &#9989; Success! Data validated.</strong>",
            "MDFormValidateError: <div class=\"important\">Unable to validate data.</div>",       
            "MDFormMailHeader: Mail Header",
            "MDFormMailFooter: Mail Footer",
            "MDFormMailValidateText: Please click the Link below to validate your data:",
            "MDFormNotifySendCopy: Please email me a copy of this form.",
            "MDFormCaptchaFormTitle: <strong>Security query:</strong>",
            "MDFormCaptchaForm: Please enter the numerical CAPTCHA from the picture in the form field.",
            "MDFormCaptchaInvalid: <div class=\"important\">The entered CAPTCHA have been incorrect.</div>",            
            "MDFormWarningRateLimit: <div class=\"important\">Warning: Please wait a moment before submitting again.</div>",
            "MDFormWarningResubmit: <div class=\"important\">Warning: Please do not resubmit successful sent forms.</div>",
            "MDFormErrorMdfFileAccess: <div class=\"important\">[mdform] Error: Form file not found or access denied.</div>",
            "MDFormErrorMdfFileNotFound: <div class=\"important\">[mdform] Error: File not found.</div>",
            "MDFormErrorTokenInvalid: <div class=\"important\">[mdform] Error: Security token invalid or expired. Please refresh page.</div>",
            "MDFormErrorCsvFileAccess: <div class=\"important\">[mdform] Error: Cannot open CSV file for writing.</div>",
            "MDFormErrorEmailSetting: <div class=\"important\">[mdform] Error: Email address settings not valid.</div>",
            "MDFormErrorEmailService: <div class=\"important\">[mdform] Error: Email not sent</div>",
            "Language: de",
            "MDFormSubmitBtn: Senden",
            "MDFormMandatory: *",
            "MDFormSubmitted: <strong> &#9989; Formular erfolgreich abgesendet.</strong>",
            "MDFormHTMLOutput: Sie haben die folgenden Daten übermittelt:",
            "MDFormCSVSaved: Daten erfolgreich gespeichert.",
            "MDFormEmailSent: Daten erfolgreich gesendet.",
            "MDFormEmailCopySend: Eine Kopie der Formulardaten wurde gesendet an: @sendermail.",
            "MDFormEmailValidateSend: Bitte validieren Sie die Daten, prüfen Sie Ihre Mailbox @sendermail und klicken Sie den Validierungslink.",
            "MDFormValidateSuccess: <strong>  &#9989; Sie haben die Daten erfolgreich validiert.</strong>",
            "MDFormValidateError: <div class=\"important\">Es war nicht möglich die Daten zu validieren.</div>",   
            "MDFormMailHeader: Mail Header",
            "MDFormMailFooter: Mail Footer",
            "MDFormMailValidateText: Bitte klicken Sie den Link unten um die Daten zu validieren:",
            "MDFormNotifySendCopy: Bitte senden Sie mir eine Kopie dieses Formulars per E-Mail.",
            "MDFormCaptchaFormTitle: <strong>Sicherheitsabfrage:</strong>",
            "MDFormCaptchaForm: <small>Bitte geben sie das numerische CAPTCHA von dem Bild in das Formularfeld ein.</small>",
            "MDFormCaptchaInvalid: <div class=\"important\">Das eingegebene CAPTCHA ist nicht korrekt.</div>",         
            "MDFormWarningRateLimit: <div class=\"important\">Warnung: Bitte warten Sie einen Moment, bevor Sie das Formular erneut absenden.</div>",
            "MDFormWarningResubmit: <div class=\"important\">Warnung: Bitte senden Sie erfolgreich abgesendete Formulare nicht mehrfach ab..</div>",
            "MDFormErrorMdfFileAccess: <div class=\"important\">[mdform] Fehler: Formulardatei nicht gefunden oder Zugriff verweigert.</div>",
            "MDFormErrorMdfFileNotFound: <div class=\"important\">[mdform] Fehler: Datei nicht gefunden.</div>",
            "MDFormErrorTokenInvalid: <div class=\"important\">[mdform] Fehler: Sicherheits-Token ungültig oder abgelaufen. Bitte Seite aktualisieren.</div>",
            "MDFormErrorCsvFileAccess: <div class=\"important\">[mdform] Fehler: CSV-Datei konnte nicht zum Schreiben geöffnet werden.</div>",
            "MDFormErrorEmailSetting: <div class=\"important\">[mdform] Fehler: E-Mail-Einstellungen sind ungültig.</div>",
            "MDFormErrorEmailService: <div class=\"important\">[mdform] Fehler: E-Mail konnte nicht gesendet werden.</div>",
        ));
        
    }

    // Page YAML Options
    // MDFormAutocomplete: OFF or ON
    // MDFormMailHeader: 
    // MDFormMailFooter: 

    // Main entry point for form element parsing
    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        
        // Only process elements named "mdform"
        if ($name == "mdform" && ($type == "block" || $type == "inline")) {
            list($fileName, $dispatchFormat, $formOptions) = $this->yellow->toolbox->getTextArguments($text); 
            $filePath = $this->yellow->system->get("MDFormDirectory");
            
            // Strip all path components to prevent directory traversal
            $fileName = basename(trim($fileName));
            
            // Verify the resolved path is within the allowed base directory
            $fullPath = realpath($filePath . $fileName);
            $basePath = realpath($filePath);
            
            // Validate path exists and is within base directory
            if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
                $output .= "<p>" . $this->yellow->language->getText("MDFormErrorMdfFileAccess") . "</p>\n ";
                return $output;
            }
            
            // Process form if filename provided
            if (!empty($fileName)) {
                // Check if the form file exists on disk
                if (file_exists($fullPath)) {
                    // Check if this is a form submission request
                    #if (($page->getRequest("mdform-status") === "send") && ($this->checkHashString($page->getRequest("mdform-file"), $fileName))) {
                    if (($page->getRequest("mdform-status") === "send") && ($this->checkHashString($page->getRequest("mdform-file"), $fileName))) {
                        $output .= $this->processSend($filePath, $fileName, $dispatchFormat, $formOptions); 
                    }
                    // Validate Form
                    #elseif ($page->getRequest("mdform-status") === "validate") {
                    elseif (($page->getRequest("mdform-status") === "validate") && ($this->checkHashString(urldecode($page->getRequest("mdform-file")), $fileName))) {
                        #echo "Validate: " . urldecode($page->getRequest("mdform-file")) . " - ". $this->checkHashString(urldecode($page->getRequest("mdform-file")), $fileName);
                        $output = $this->processValidateCSV($fileName, urldecode($page->getRequest("mdform-hash")));
                    }
                    // Render the form for standard display
                    else {
                        $output = $this->getFormHTML($filePath, $fileName, $formOptions, false);                      
                    }
                } 
                // Handle case where file is missing
                else {
                    $output .= "<p>" . $this->yellow->language->getText("MDFormErrorMdfFileNotFound") . "</p>\n ";
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
            // CSS
            $style = $this->yellow->system->get("MDFormStyleSheet");
            // Append link tag if stylesheet is defined
            if ($style != "") {
                $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$assetLocation}{$style}\" />\n";
            }
            // JS
            $jsFile = $this->yellow->system->get("MDFormJavaScript");         
            // Add JavaScript libary
            if (($jsFile != "") && ($this->yellow->system->get("MDFormActivateJavaScript"))) {
                 $output .= "<script src=\"{$assetLocation}{$jsFile}\" defer></script>\n";
            }
        }
        return $output;
    }

    // Loads and validates form definition files
    private function getFormHTML($filePath, $fileName, $formOptions, $prefillValues = false) {
        $fileType = $this->yellow->toolbox->getFileType($filePath . $fileName);
        $allowed = $this->yellow->system->get("MDFormAllowedExtensions");
        $allowed = array_map('trim', explode(',', $allowed));

        // Only process allowed file extensions
        if (is_array($allowed) && in_array($fileType, $allowed)) {
            $formData = $this->readMarkdown(file_get_contents($filePath . $fileName));
            return $this->generateHTMLForm($formData, $fileName, $formOptions, $prefillValues);
        }
        return false;
    }

    // Parses markdown-formatted form definition into a structured array
    private function readMarkdown($fileContent) {
        $lines = explode("\n", $fileContent);
        $formData = [];
        $counters = ['radio' => 0, 'check' => 0, 'toggle' => 0, 'input' => 0];

        // Process form markdown line by line
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

            // Output references: Label 1: {output:Name}      
            if (preg_match('/^(?:(.+?):\s*)?\{output:(.+)\}$/', $cleanLine, $outputMatches)) {
                // If the label part matched, it's in index 1. Otherwise, it's an empty string.
                $labelPrefix = !empty($outputMatches[1]) ? trim($outputMatches[1]) : '';
                $outputName = trim($outputMatches[2]); 
                
                $entry['type'] = 'output';
                $entry['label'] = $labelPrefix;
                $entry['for'] = $this->sanitizeFromName($outputName);
                $entry['name'] = 'output_' . $entry['for'];
                
                $formData[] = $entry;
                continue;
            }

            // Extract label and body for form fields
            if (preg_match('/^(.+?):\s*(\[.*\].*)$/', $cleanLine, $matches)) {
                $labelPrefix = trim($matches[1]);
                $elementBody = trim($matches[2]);
            }

            #$entry = ['label' => $labelPrefix, 'required' => $isMandatory, 'name' => '', 'type' => '', 'options' => [], 'placeholder' => '', 'autocomplete' => '', 'min' => '', 'max' => ''];
            $entry = ['label' => $labelPrefix, 'required' => $isMandatory, 'name' => '', 'type' => '', 'options' => [], 'placeholder' => '', 'autocomplete' => ''];

            // Extract special attributes if present
            if (preg_match('/^(.*)\{(\_[a-z0-9\-\_]+)\}\s*$/i', $elementBody, $attrMatches)) {
                $elementBody = trim($attrMatches[1]);
                $entry['special'] = strtolower(trim($attrMatches[2]));
            }
            
            // Extract autocomplete attributes if present
            if (preg_match('/^(.*)\{([a-z0-9\-]+)\}\s*$/i', $elementBody, $attrMatches)) {
                $elementBody = trim($attrMatches[1]);
                $entry['autocomplete'] = strtolower(trim($attrMatches[2]));
            }

            // Select dropdown elements
            if (preg_match('/^\[(.*?)\s*▼\s*;\s*(.*)\]$/u', $elementBody, $matches)) {
                $entry['type'] = 'select';
                $entry['name'] = $this->sanitizeFromName($labelPrefix ?: "dropdown_" . (++$counters['input']));
                $entry['placeholder'] = trim($matches[1]);
                $entry['options'] = array_map('trim', explode(',', $matches[2]));
            } 
            // Radio button groups
            elseif (preg_match('/^\[(\(\s*[xX ]?\s*\).*?)\]$/', $elementBody, $matches)) {
                $entry['type'] = 'radio';
                $entry['name'] = $this->sanitizeFromName($labelPrefix ?: "radio_group_" . (++$counters['radio']));
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
            // Checkbox groups
            elseif (preg_match('/^\[(\[\s*[xX ]?\s*\].*?)\]$/', $elementBody, $matches)) {
                $entry['type'] = 'checkbox';
                $entry['name'] = $this->sanitizeFromName($labelPrefix ?: "check_group_" . (++$counters['check']));
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
            // Toggle inputs [ON/OFF]
            elseif (preg_match('/^\[(?:(.*?):\s*)?ON\/OFF\]$/i', $elementBody, $matches)) {
                $entry['type'] = 'toggle';
                $toggleName = isset($matches[1]) ? trim($matches[1]) : "";
                $entry['name'] = $this->sanitizeFromName($toggleName ?: $labelPrefix ?: "toggle_" . (++$counters['toggle']));
            }
            // Toggle inputs [OFF/ON]
            elseif (preg_match('/^\[(?:(.*?):\s*)?OFF\/ON\]$/i', $elementBody, $matches)) {
                $entry['type'] = 'toggle';
                $toggleName = isset($matches[1]) ? trim($matches[1]) : "";
                $entry['name'] = $this->sanitizeFromName($toggleName ?: $labelPrefix ?: "toggle_" . (++$counters['toggle']));
                $entry['options'][] = ['checked' => true];
            }
            // Date inputs
            elseif (preg_match('/^\[DD\/MM\/YYYY(?:;(\d{4}-\d{2}-\d{2}|TODAY)\.\.(\d{4}-\d{2}-\d{2}|TODAY))?\]$/', $elementBody, $dateMatches)) {
                $entry['type'] = 'date';
                $entry['name'] = $this->sanitizeFromName($labelPrefix ?: "date_" . (++$counters['input']));
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
                    $entry['name'] = $this->sanitizeFromName($labelPrefix ?: $entry['placeholder'] ?: "textarea_" . (++$counters['input']));
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
                    $entry['name'] = $this->sanitizeFromName($labelPrefix ?: "number_" . (++$counters['input']));
                }
                // Hidden inputs: [~Name: Value]
                elseif (preg_match('/^\[(~)(.+):\s*(.+)\]$/', $elementBody, $matches)) {
                    $entry['type'] = 'hidden';
                    $entry['name'] = $this->sanitizeFromName(trim($matches[2]));
                    $entry['value'] = trim($matches[3]);
                    $entry['required'] = false; // Hidden fields are never required
                }
                // Default to standard text field
                else {
                    $entry['type'] = 'text';
                    $entry['placeholder'] = trim($rawContent, '. ');
                    $entry['name'] = $this->sanitizeFromName($labelPrefix ?: $entry['placeholder'] ?: "text_" . (++$counters['input']));
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
    private function generateHTMLForm($formData, $fileName, $formOptions, $prefillValues) {
        $fileHash = $this->createHashString($fileName); 
        $autocompleteValue = strtolower($this->yellow->page->get("MDFormAutocomplete"));

        $output = "<div class=\"mdform-container\">\n  <form id=\"" . htmlspecialchars($fileHash) . "\" method=\"post\"";
        // Allow to turn off autocomplete
        if (in_array($autocompleteValue, ['on', 'off'])) {
            $output .= " autocomplete=\"" . $autocompleteValue . "\"";
        }
        $output .= ">\n";

        $output .= "    <input type=\"hidden\" name=\"mdform-file\" value=\"" . htmlspecialchars($fileHash) . "\">\n";

        #var_dump($formData); // Just for data structure debugging purpose

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
                $output .= "      <strong>{$field['label']}: $star</strong><br />\n";
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
                // Render output elements
                case 'output':
                    $output .= "      <output name=\"{$field['name']}\" id=\"{$field['name']}\" for=\"{$field['for']}\"></output>\n";
                    break;
                // Render select dropdowns
                case 'select':
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequestHtml($field['name']) : "";
                    $output .= "      <select name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control\" $req";
                    // Append autocomplete attribute
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= " style=\"width:100%\" >\n";
                    $output .= "        <option value=\"\">{$field['placeholder']}</option>\n";
                    // Add dropdown options
                    foreach ($field['options'] as $option) {
                        $output .= "        <option value=\"$option\"" . (($option === $value) ? " selected": "") . ">$option</option>\n";
                    }
                    $output .= "      </select>\n";
                    break;
                // Render radio buttons
                case 'radio':
                     $value = ($prefillValues === true) ? $this->yellow->page->getRequest($field['name']) : "";
                    // Loop through radio options
                    foreach ($field['options'] as $option) {
                        $output .= "      <label><input type=\"radio\" name=\"{$field['name']}\" class=\"form-control\" value=\"{$option['value']}\" $req";
                        // Add autocomplete
                        if ($field['autocomplete']) {
                            $output .= " autocomplete=\"{$field['autocomplete']}\"";
                        }
                        // Set checked state
                        if ($option['checked'] && ($prefillValues !== true)) {
                            $output .= " checked";
                        }
                        // Set checked state during resubmit
                        if ($prefillValues === true && ($value === $option['value'])) {
                            $output .= " checked";
                        }
                        $output .= "> {$option['value']}</label> \n";
                    }
                    break;   
                // Render checkboxes
                case 'checkbox':
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequest($field['name']) : "";
                    // Loop through checkbox options
                    foreach ($field['options'] as $option) {
                        $output .= "      <label><input type=\"checkbox\" name=\"{$field['name']}[]\" class=\"form-control\" value=\"{$option['value']}\" $req";
                        // Add autocomplete
                        if ($field['autocomplete']) {
                            $output .= " autocomplete=\"{$field['autocomplete']}\"";
                        }
                        // Set checked state
                        if ($option['checked'] && ($prefillValues !== true)) {
                            $output .= " checked";
                        }
                        // Set checked state during resubmit
                        if ($prefillValues === true) {
                            foreach ($value as $val) {
                                ($val === $option['value']) ? $output .= " checked" : $output .= "";
                            }
                        }
                        $output .= "> {$option['value']}</label> \n";
                    }
                    break;
                // Render toggle switch
                case 'toggle':
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequest($field['name']) : "";
                    $output .= "      <input type=\"checkbox\" name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control switch\" $req value=\"ON\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    // Set checked state
                    if (isset($field['options'][0]['checked']) && ($prefillValues !== true)) {
                        $output .= " checked";
                    } 
                    elseif ($prefillValues === true && $value === "ON") {
                        $output .= " checked";
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
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequestHtml($field['name']) : "";
                    $output .= "      <input type=\"date\" name=\"{$field['name']}\" id=\"{$field['name']}\" value=\"$value\" class=\"form-control\"";
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
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequestHtml($field['name']) : "";
                    $output .= "      <input type=\"number\" name=\"{$field['name']}\" id=\"{$field['name']}\" value=\"$value\" class=\"form-control\"";
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
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequestHtml($field['name']) : "";
                    $output .= "      <textarea name=\"{$field['name']}\" id=\"{$field['name']}\" class=\"form-control\" placeholder=\"{$field['placeholder']}\" $req style=\"width:100%\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= " rows=\"{$field['min']}\">$value</textarea>\n";
                    break;
                // Render standard text inputs
                case 'text':
                    $value = ($prefillValues === true) ? $this->yellow->page->getRequestHtml($field['name']) : "";
                    $output .= "      <label><input type=\"$htmlType\" name=\"{$field['name']}\" id=\"{$field['name']}\" value=\"$value\" class=\"form-control\" placeholder=\"{$field['placeholder']}\" $req style=\"width:100%\"";
                    // Add autocomplete
                    if ($field['autocomplete']) {
                        $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    }
                    $output .= "></label>\n";
                    break;
                // Render hidden inputs
                case 'hidden':
                    $value = isset($field['value']) ? htmlspecialchars($field['value']) : '';
                    $output .= "      <input type=\"hidden\" name=\"{$field['name']}\" id=\"{$field['name']}\" value=\"$value\">\n";
                    break;
            }
            $output .= "    </p>\n";
        }

        // Create CAPTCHA block
        if (stripos($formOptions, "captcha") !== false) {
            $captcha = $this->getRandomCaptchaString(); // Create an random captcha sting
            $output .= "    <blockquote><p>" . $this->yellow->language->getText("MDFormCaptchaFormTitle") . "<br />" . $this->getCaptcha($captcha) . "<br />&rdsh;<input type=\"number\" name=\"captcha\" placeholder=\"CAPTCHA\" pattern=\"[0-9]{6}\" size=\"6\" step=\"1\"  min=\"0\"  max=\"999999\" maxlength=\"6\"><br />";
            $output .= $this->yellow->language->getText("MDFormCaptchaForm")  . "</p>\n</blockquote>\n"; 
            $output .= "    <input type=\"hidden\" name=\"captcha_hash\" value=\"".$this->createCaptchaHash($captcha)."\" />\n";
        }

        $csrfToken = $this->createHashString($this->yellow->system->get("MDFormHashSaltPasskey"));
        $output .= "    <input type=\"hidden\" name=\"mdform-hash\" value=\"" . htmlspecialchars($csrfToken) . "\" />\n";
        $output .= "    <input type=\"hidden\" name=\"mdform-referer\" value=\"" . $this->yellow->toolbox->getServer("HTTP_REFERER") . "\" />\n";
        $output .= "    <input type=\"hidden\" name=\"mdform-status\" value=\"send\" />\n";        
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
    
    // Extracts ONLY data-holding fields from MDF file for backend processing
    private function getFormFileMetadata($fileContent) {
        $formData = $this->readMarkdown($fileContent);
        $formStructure = [];
        
        // Loop through fields to extract names and requirements
        foreach ($formData as $field) {
            // SKIP display-only markdown elements
            if (!($field['type'] === 'markdown')) {
                // Define the base structure
                $structure = [
                    'name'         => $field['name'], 
                    'required'     => $field['required'],
                    'type'         => $field['type'],
                    'autocomplete' => $field['autocomplete'],
                ];

                // Only add 'special' if it exists and is not null
                if (isset($field['special'])) {
                    $structure['special'] = $field['special'];
                }
                $formStructure[$field['name']] = $structure;
            }
        }

        #var_dump($formStructure); // Just for data structure debugging purpose
        return $formStructure;
    }

    // Handles form submission security and dispatch logic
    private function processSend($filePath, $fileName, $dispatchFormat, $formOptions) {
        
        // Validate CSRF token before processing
        $receivedHash = $this->yellow->page->getRequest("mdform-hash");
        if (!$this->checkHashString($receivedHash, $this->yellow->system->get("MDFormHashSaltPasskey"))) {
            return "<p>" . $this->yellow->language->getText("MDFormErrorTokenInvalid") . "</p>\n " . $this->getFormHTML($filePath, $fileName, $formOptions, false);
        }

        // Verify if IP is being rate limited
        if ($this->isRateLimited()) {
            return "<p>" . $this->yellow->language->getText("MDFormWarningRateLimit") . "</p>\n " . $this->getFormHTML($filePath, $fileName, $formOptions, true);
        }
        
        // Validate Create CAPTCHA 
        if (stripos($formOptions, "captcha") !== false) {
            if (!$this->checkCaptcha(trim($this->yellow->page->getRequest("captcha")), trim($this->yellow->page->getRequest("captcha_hash")))) {
                return "<p>" . $this->yellow->language->getText("MDFormCaptchaInvalid") . "</p>\n " . $this->getFormHTML($filePath, $fileName, $formOptions, true);
            }
        }
        
        if ($this->yellow->system->get("MDFormResubmitCookie"))  {
            // Validate resubmit with token
            if ($this->yellow->toolbox->getCookie($receivedHash) === "yellowmdformhash") {
                return "<p>" . $this->yellow->language->getText("MDFormWarningResubmit") . "</p>\n " . $this->getFormHTML($filePath, $fileName, $formOptions, true);
            } else {
                // Create a Cookie
                $this->createCookie($receivedHash, "yellowmdformhash");
                // Destroy Cookie
                #$this->destroyCookie($receivedHash);
            } 
        }

        $output = "<div class=\"mdform-container\">\n ";
        $output .= "<p>" . $this->yellow->language->getText("MDFormSubmitted") . "</p>\n ";
        
        // Execute dispatch methods if defined
        if (!is_string_empty($dispatchFormat)) {
            $formStructure = $this->getFormFileMetadata(file_get_contents($filePath . $fileName));
            #var_dump($formStructure); // Just for data structure debugging purpose
            $dispatchCommands = preg_split('/[\s,]+/', $dispatchFormat, -1, PREG_SPLIT_NO_EMPTY);

            // Execute each dispatch method in order
            foreach ($dispatchCommands as $cmd) {
                // Handle HTML display dispatch
                if (strtolower($cmd) === "html") {
                    $output .= $this->subDispatchHtml($formStructure);
                } 
                // Handle CSV export dispatch
                elseif (strtolower($cmd) === "csv") {
                    $output .= $this->subDispatchCsv($formStructure, $fileName);
                } 
                // Handle Email notification dispatch
                elseif (strtolower($cmd) === "email") {
                    $output .= $this->subDispatchEmail($formStructure);
                }
            }
        }
        $output .= "</div>\n ";
        return $output;
    }

    // Cleans field names by replacing special characters with underscores
    private function sanitizeFromName($name) {
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

        
        // Create unique visitor fingerprint
        $fingerprint = hash("sha256", $ip . '|' . $userAgent);
        $fingerprintLimitFile = $limitDir . "fp_" . $fingerprint;
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

    // Create browser cookies
    private function createCookie($name, $value) {
        $expire = 0;
        $scheme = $this->yellow->system->get("coreServerScheme");
        $address = $this->yellow->system->get("coreServerAddress");
        $base = $this->yellow->system->get("coreServerBase");
        $path = $this->yellow->page->getLocation();
        setcookie($name, $value, $expire, $base.$path, "", $scheme=="https", true);
    }
    
    // Destroy browser cookies
    private function destroyCookie($name) {
        $scheme = $this->yellow->system->get("coreServerScheme"); // Match creation logic
        $base = $this->yellow->system->get("coreServerBase");
        $path = $this->yellow->page->getLocation();
        setcookie($name, "", 1, $base.$path, "", $scheme=="https", true);
    }
    
    // Dispatch: Formats submitted data as HTML
    private function subDispatchHtml($formStructure) {
        $output = "<p>" . $this->yellow->language->getText("MDFormHTMLOutput") . "</p>\n"; 
        $output .= "<table> \n"; 
        // Iterate through submission headers
        foreach (array_keys($formStructure) as $header) {
            $val = $this->yellow->page->getRequest($header);
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $displayVal = ($formStructure[$header]['type'] === 'toggle' && empty($displayVal)) ? "OFF": $displayVal; // Toggle: Show OFF value if not ON value
            $displayVal = htmlspecialchars(trim($displayVal));
            $output .= "<tr><td>" . htmlspecialchars($header) . $this->yellow->system->get("MDFormKeyValueSeperator") . "</td><td><strong>" . $displayVal . "</strong></tr>\n";
        }
        $output .= "</table> \n"; 
        return $output;
    }

    // Dispatch: Appends submitted data to a CSV file
    private function subDispatchCsv($formStructure, $fileName)  {
        $output = ""; 
        $delimiter = ",";
        $enclosure = '"';
        $escape = "\\";
        
        $csvPath = $this->yellow->system->get("MDFormDirectoryCSVOutput") . $fileName . ".csv"; 
        $expectedHeaders = array_merge(["mdform-timestamp"], ["mdform-hash"], ["mdform-validated"], array_keys($formStructure));  // Store also the mdform-hash within the CSV data
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
            // Filing 
            if ($header === "mdform-timestamp") {
                $val = date("Y-m-d H:i:s");
            } 
            elseif ($header === "mdform-validated") {
                $val = "0";
            } 
            else {
                $val =  $this->yellow->page->getRequest($header);
            }
            // Join array values with semicolons
            if (is_array($val)) {
                $val = implode("; ", $val); 
            }
            
            $dataRow[] = is_string($val) ? $val : (string)$val;
        }

        $isNew = !file_exists($csvPath);
        $handle = fopen($csvPath, 'a');
        
        // Handle CSV file write failures
        if ($handle === false) {
            return "<p>" . $this->yellow->language->getText("MDFormErrorCsvFileAccess") . "</p>\n ";
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

    // Validates a previously submitted form entry in the CSV
    private function processValidateCSV($fileName, $formHash) {
        $output = ""; 
        $delimiter = ",";
        $enclosure = '"';
        $escape = "\\";
        $found = false;
        
        $csvPath = $this->yellow->system->get("MDFormDirectoryCSVOutput") . $fileName . ".csv"; 
        
        if (!file_exists($csvPath)) {
            return "<p>" . $this->yellow->language->getText("MDFormErrorMdfFileNotFound") . "</p>\n";
        }

        $rows = [];
        $headers = [];

        // 1. Read the file into memory
        if (($handle = fopen($csvPath, "r")) !== false) {
            // Get the header row first
            $headers = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
            
            if ($headers !== false) {
                // Find the column indexes for hash and validation status
                $hashIndex = array_search("mdform-hash", $headers);
                $validatedIndex = array_search("mdform-validated", $headers);

                if ($hashIndex !== false && $validatedIndex !== false) {
                    // Read data rows
                    while (($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                        // Check if this row matches the hash
                        if ($data[$hashIndex] === $formHash) {
                            $data[$validatedIndex] = "1"; // Change "0" to "1"
                            $found = true;
                        }
                        $rows[] = $data;
                    }
                }
            }
            fclose($handle);
        }

        // 2. If a match was found, write the updated data back to the CSV
        if ($found) {
            $handle = fopen($csvPath, 'w'); // 'w' truncates the file to overwrite
            if ($handle !== false) {
                fputcsv($handle, $headers, $delimiter, $enclosure, $escape);
                foreach ($rows as $row) {
                    fputcsv($handle, $row, $delimiter, $enclosure, $escape);
                }
                fclose($handle);
                $output = "<p>" . $this->yellow->language->getText("MDFormValidateSuccess") . "</p>\n";
            } else {
                $output = "<p>" . $this->yellow->language->getText("MDFormErrorCsvFileAccess") . "</p>\n";
            }
        } else {
            // If hash is not found, it might be expired or already validated
            $output = "<p>" . $this->yellow->language->getText("MDFormValidateError") . "</p>\n"; # . $this->getFormHTML($filePath, $fileName, $formOptions, true);
        }

        return $output;
    }

    // Dispatch: Sends submitted data via email
    private function subDispatchEmail($formStructure) {
        $output = "";
        $message = "";
        $sendCc = false;
        $sendValidate = false;
        
        // Construct email body from submission
        $maxKeyLength = max(array_map('mb_strlen', array_keys($formStructure)));
        foreach (array_keys($formStructure) as $header) {
            $val = $this->yellow->page->getRequest($header);
            $displayKey = htmlspecialchars($header);
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $displayVal = ($formStructure[$header]['type'] === 'toggle' && empty($displayVal)) ? "OFF": $displayVal; // Toggle: Show OFF value if not ON value
            $displayVal = htmlspecialchars(trim($displayVal));
            $paddingCount = ($maxKeyLength - mb_strlen($displayKey));
            $spaces = str_repeat(" ", $paddingCount);
            $message  .= "{$displayKey}{$this->yellow->system->get("MDFormKeyValueSeperator")}{$spaces}{$displayVal}\r\n";
            
            if (isset($formStructure[$header]['special']) && $formStructure[$header]['special'] === '_cc_email' && $this->yellow->page->getRequest($header) === 'ON') {
                $sendCc = true;
            }
            if (isset($formStructure[$header]['special']) && $formStructure[$header]['special'] === '_validate_email' && $this->yellow->page->getRequest($header) === 'ON') {
                $sendValidate = true;
            }
        }
        
        $referer = trim($this->yellow->page->getRequest("mdform-referer"));
        $sitename = $this->yellow->system->get("sitename");
        $siteEmail = $this->yellow->system->get("from");
        $subject = $this->yellow->page->get("title") . " - " . $sitename;
        
        // Get mail senders detail
        $userName = $this->yellow->system->get("author");
        $userEmail = $this->yellow->system->get("email");

        // Apply page-specific author overrides if allowed
        if ($this->yellow->page->isExisting("author") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $userName = $this->yellow->page->get("author");
        }
        // Apply page-specific email overrides if allowed
        if ($this->yellow->page->isExisting("email") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $userEmail = $this->yellow->page->get("email");
        }
        
        // Get form senders contact from autocomplete fields
        $senderName = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $this-> getSenderName($formStructure)));
        $senderEmail = trim($this-> getSenderEmail($formStructure));        
        
        // Get mail text header and footer
        $headerText = $this->yellow->language->getText("MDFormMailHeader");
        $footerText = $this->yellow->language->getText("MDFormMailFooter");
        
        // Apply page-specific header overrides if allowed
        if ($this->yellow->page->isExisting("MDFormMailHeader") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $headerText = $this->yellow->page->get("MDFormMailHeader");
        }
        // Apply page-specific footer overrides if allowed
        if ($this->yellow->page->isExisting("MDFormMailFooter") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $footerText = $this->yellow->page->get("MDFormMailFooter");
        }
     
        $headerText = str_replace("\\n", "\r\n", $headerText);
        $headerText = preg_replace("/@sendershort/i", strtok($senderName, " "), $headerText);
        if (!empty($senderEmail)) {
            $headerText = preg_replace("/@sender/i", "$senderName <$senderEmail>", $headerText);
        } else {
            $headerText = preg_replace("/@sender/i", $senderName, $headerText);
        }


        $footerText = str_replace("\\n ", "\n", $footerText);
        $footerText = str_replace("\\n", "\r\n", $footerText);
        $footerText = preg_replace("/@sitename/i", $this->yellow->system->get("sitename"), $footerText);
        $footerText = preg_replace("/@sitemail/i", $this->yellow->system->get("from"), $footerText);
        $footerText = preg_replace("/@usermail/i", $this->yellow->system->get("email"), $footerText);
        $footerText = preg_replace("/@author/i", $this->yellow->page->get("author"), $footerText);
        $footerText = preg_replace("/@title/i", $this->yellow->page->get("title"), $footerText);

        
        // Sanitize Inputs
        $userName = $this->sanitizeEmailName($userName);
        $userEmail = $this->sanitizeEmailAddress($userEmail);
        $sitename = $this->sanitizeEmailName($sitename);
        
        // Validate sender email address format
        if (is_string_empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $output = "<p>" . $this->yellow->language->getText("MDFormErrorEmailSetting") . "</p>\n ";
            return $output;
        }
        
        $mailTo = "$userName <$userEmail>";
        $mailFrom = "$sitename <$siteEmail>";
        $mailSubject = $this->sanitizeEmailSubject($subject);
        $mailMessage = "$headerText\r\n\r\n$message\r\n--\r\n$footerText";
        
         // Reply-To if a email address is given
        $mailReplyTo = (!empty($senderEmail) && (filter_var($senderEmail, FILTER_VALIDATE_EMAIL))) ? "$senderName <$senderEmail>" : "";
  
        // Send mail to page author
        $output = $this->sendEmail($mailTo, $mailFrom, $mailSubject, $mailMessage, $mailReplyTo)
            ? ("<p>" . $this->yellow->language->getText("MDFormEmailSent") . "</p>\n")
            : "<p>". $this->yellow->language->getText("MDFormErrorEmailService") ."</p>";

        // Send copy mail to from sender
        if ($sendCc === true && (filter_var($senderEmail, FILTER_VALIDATE_EMAIL))) {
            $mailTo = "$senderName <$senderEmail>";
            $mailFrom = "$sitename <$siteEmail>";
            $mailSubject = $this->sanitizeEmailSubject($subject);
            $mailMessage = "$headerText\r\n\r\n$message\r\n--\r\n$footerText";
            
            $output .= $this->sendEmail($mailTo, $mailFrom, $mailSubject, $mailMessage)
                ? ("<p>" . preg_replace("/@sendermail/i", $senderEmail, $this->yellow->language->getText("MDFormEmailCopySend")) . "</p>\n")
                : "<p>". $this->yellow->language->getText("MDFormErrorEmailService") ."</p>";
        }
        
        // Send validation mail to from sender
        if ($sendValidate === true && (filter_var($senderEmail, FILTER_VALIDATE_EMAIL))) {
            $mailTo = "$senderName <$senderEmail>";
            $mailFrom = "$sitename <$siteEmail>";
            $mailSubject = $this->sanitizeEmailSubject($subject);
            $mailValidationFile = urlencode($this->yellow->page->getRequest("mdform-file"));
            $mailValidationHash = urlencode($this->yellow->page->getRequest("mdform-hash"));
            $mailValidationLink = filter_var($this->yellow->toolbox->getServer("HTTP_REFERER"), FILTER_SANITIZE_URL) . "?mdform-status=validate&mdform-file=$mailValidationFile&mdform-hash=$mailValidationHash";
            $mailValidationText = $this->yellow->language->getText("MDFormMailValidateText");
            $mailMessage = "$headerText\r\n\r\n$message\r\n--\r\n$mailValidationText\r\n$mailValidationLink\r\n--\r\n$footerText";
            
            $output .= $this->sendEmail($mailTo, $mailFrom, $mailSubject, $mailMessage)
                ? ("<p>" . preg_replace("/@sendermail/i", $senderEmail, $this->yellow->language->getText("MDFormEmailValidateSend")) . "</p>\n")
                : "<p>". $this->yellow->language->getText("MDFormErrorEmailService") ."</p>";
        }
        
        return $output;
    }
    
    
    // Send Email
    private function sendEmail($to, $from, $subject, $body, $replpyto = "", $cc = "", $bcc = "") {
    
        $referer = filter_var($this->yellow->toolbox->getServer("HTTP_REFERER"), FILTER_SANITIZE_URL);
        
        // Construct email header array
        $mailHeaders = array(
            "To" => $this->yellow->lookup->normaliseAddress($to),
            "From" => $this->yellow->lookup->normaliseAddress($from),
            "Subject" => $subject,
            "Date" => date(DATE_RFC2822),
            "Mime-Version" => "1.0",
            "Content-Type" => "text/plain; charset=utf-8",
            "X-Referer-Url" => $referer,
            "X-Request-Url" => $this->yellow->page->getUrl()
        );
        // Conditionally add to header
        if (!empty($cc)) {
                $mailHeaders["Cc"] = $this->yellow->lookup->normaliseAddress($cc);
        }
        if (!empty($bcc)) {
                $mailHeaders["Bcc"] = $this->yellow->lookup->normaliseAddress($bcc);
        }
        if (!empty($replpyto)) {
                $mailHeaders["Reply-To"] = $this->yellow->lookup->normaliseAddress($replpyto);
        }
        $mailMessage = $body;
        
        // Execute system mail function
        return $this->yellow->toolbox->mail("MDForm", $mailHeaders, $mailMessage);

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
    
    // Find input name by the first input filed defined as [ ]{name}
     private function getSenderName($formStructure) {
        foreach ($formStructure as $field) {
            // Check if autocomplete is correspding "name"
            if ($field['autocomplete'] === "name") {
                return htmlspecialchars($this->yellow->page->getRequest($field['name']));
            }
        }
    }
    
    // Find input name by the first input filed defined as [ ]{email}
     private function getSenderEmail($formStructure) {
        foreach ($formStructure as $field) {
            // Check if autocomplete is correspding "email"
            if ($field['autocomplete'] === "email") {
                return htmlspecialchars($this->yellow->page->getRequest($field['name']));
            }
        }
    }

    // Check if text contains clickable links
    public function checkClickable($text) {
        $found = false;
        foreach (preg_split("/\s+/", $text) as $token) {
            if (preg_match("/([\w\-\.]{2,}\.[\w]{2,})/", $token)) $found = true;
            if (preg_match("/^\w+:\/\//", $token)) $found = true;
        }
        return $found;
    }

    // Create captcha string
    public function getRandomCaptchaString($length = 6) {
        $stringSpace = '0123456789';
        $stringLength = strlen($stringSpace);
        $randomString = '';
        for ($i = 0; $i < $length; $i ++) {
            $randomString = $randomString . $stringSpace[rand(0, $stringLength - 1)];
        }
        return $randomString;
    }

    // Create captcha hash
    public function createCaptchaHash($string) {
        $hash = $this->yellow->toolbox->createHash($string, "sha256");
        if (is_string_empty($hash)) $hash = "padd"."error-hash-algorithm-sha256";
        return $hash;
    }
       
    // Create captcha image
    public function getCaptcha($string) {

        // Begin output buffering
        ob_start();

        // generate the captcha image in some magic way
        $w = 80; $h = 30;
        $image = imagecreate($w, $h);
        $background = imagecolorallocatealpha($image, 127, 127, 127, 63);
        imagefill($image, 0, 0, $background);

        $color[0] = imagecolorallocate($image, 0, 0, 0);    
        $color[1] = imagecolorallocate($image, 255, 255, 255);

        $strlen = strlen($string);
        for( $i = 0; $i < $strlen; $i++ ) {
            $char = substr( $string, $i, 1 );
            $s = rand(0, 9);
            $x = $i * 10;
            $y = rand(0, 9);
            $c = rand(0, 1);

            imagechar($image, 4, $x + 12, $y + 3, $char, $color[$c]);
            if ($y <= 3) imagechar($image, 4, $x + 12, $y + 6, "_", $color[(1-$c)]);
            if ($y >= 6) imagechar($image, 4, $x + 12, $y - 12, "_", $color[(1-$c)]);
        }  
        imagepng($image);

        // and finally retrieve the byte stream
        $rawImageBytes = ob_get_clean();

        imageDestroy($image);

        return '<img src="data:image/png;base64,'. base64_encode( $rawImageBytes ) . '">';

    }

    // Check captcha
    public function checkCaptcha($string, $hash) {
        return $this->yellow->toolbox->verifyHash($string, "sha256", $hash);
    }

}
