<?php
/**
 * ============================================================================
 * MDForm - Markdown Form Extension for Datenstrom Yellow
 * ============================================================================
 * 
 * DESCRIPTION:
 * This extension allows creating HTML forms from markdown-formatted files.
 * Forms can be submitted with multiple dispatch options (HTML display, CSV export, Email).
 * 
 * FEATURES:
 * - Markdown-based form definition (.mdf, .fmd, .md, .form files)
 * - CSRF protection via time-based hash validation
 * - Rate limiting to prevent spam submissions
 * - Multiple field types: text, textarea, select, radio, checkbox, toggle, date
 * - Multi-language support (English/German defaults)
 * - Configurable output directories and email settings
 * 
 * SECURITY FEATURES:
 * - Path traversal protection (basename() validation)
 * - CSRF token validation with IP binding
 * - Rate limiting per IP address
 * - Email header injection prevention
 * - Input sanitization for email fields
 * 
 * AUTHOR: Andreas Städler
 * VERSION: 0.0.1
 * DATE: 17.04.2026
 * LICENSE: See extension repository
 * 
 * CONFIGURATION SETTINGS:
 * - MDFormDirectory: Directory containing form definition files
 * - MDFormDirectoryCSVOutput: Directory for CSV output files
 * - MDFormHashPasskey: Secret key for CSRF hash generation (CHANGE THIS!)
 * - MDFormRateLimitDirectory: Directory for rate limit tracking files
 * - MDFormEmail: Sender email address for form notifications
 * - MDFormEmailRestriction: Whether to restrict email from page metadata
 * - MDFormLinkRestriction: Whether to restrict clickable links in emails
 * 
 * USAGE IN YELLOW PAGES:
 * [mdform form-name]           - Display form
 * [mdform form-name html]      - Display form with HTML output on submit
 * [mdform form-name csv]       - Display form with CSV export on submit
 * [mdform form-name email]     - Display form with email notification on submit
 * [mdform form-name html,csv,email] - All dispatch methods combined
 * 
 * MARKDOWN FORM SYNTAX:
 * Label: [Placeholder]          - Text input
 * Label*: [Placeholder]         - Required text input (*)
 * Label: [Dropdown ▼ Option1,Option2] - Dropdown select
 * Label: [( ) Option1, ( ) Option2]   - Radio buttons
 * Label: [(x) Option1, ( ) Option2]   - Radio with default selection
 * Label: [[x] Option1, [ ] Option2]   - Checkboxes
 * Label: Field Name: ON/OFF     - Toggle switch
 * Label: [DD/MM/YYYY]           - Date picker
 * Label: [Multi-line...]        - Textarea
 * 
 * ============================================================================
 */

class YellowMdform {
    /**
     * Extension version number
     * @var string
     */
    const VERSION = "0.0.1";
    
    /**
     * Reference to Yellow CMS API instance
     * @var object
     */
    public $yellow;

    /**
     * ========================================================================
     * INITIALIZATION
     * ========================================================================
     * 
     * Called when the extension loads. Sets up default configuration values
     * and language strings for the form extension.
     * 
     * @param object $yellow Yellow CMS API instance
     */
    public function onLoad($yellow) {
        $this->yellow = $yellow;
        
        // Directory for storing form definition files (.mdf, .fmd, .md, .form)
        $this->yellow->system->setDefault("MDFormDirectory", "media/forms/");
        
        // Directory for storing CSV output files from form submissions
        $this->yellow->system->setDefault("MDFormDirectoryCSVOutput", "media/tables/");
        
        // SECRET: Passkey for CSRF hash generation - MUST be changed in production!
        // This secret is combined with IP and timestamp to create unique tokens
        $this->yellow->system->setDefault("MDFormHashPasskey", "some nonsense string");
        
        // Directory for rate limiting files (stores submission timestamps per IP)
        $this->yellow->system->setDefault("MDFormRateLimitDirectory", "cache/mdform/ratelimit/");
        
        // Default sender email address for form notification emails
        $this->yellow->system->setDefault("MDFormEmail", "noreply@server.com");
        
        // Email restriction flag: 0 = allow page metadata emails, 1 = use system default only
        $this->yellow->system->setDefault("MDFormEmailRestriction", "0");
        
        // Link restriction flag: 1 = disable clickable links in email content
        $this->yellow->system->setDefault("MDFormLinkRestriction", "1");
        
        // Allowed file extensions for form definition files
        $this->yellow->system->setDefault("MDFormAllowedExtensions", ['mdf', 'fmd', 'md', 'form']);
 
         // Allowed file extensions for form definition files
        $this->yellow->system->setDefault("MDFormStyleSheet", "");
        
        // Language translations for UI messages
        $this->yellow->language->setDefaults(array(
            // English
            "Language: en",
            "MDFormMandatory: *",
            "MDFormSubmitted: <strong>Form successfully submitted</strong>",
            "MDFormCSVSaved: Success! Data saved",
            "MDFormEmailSend: Success! Data send",
            "MDFormMailHeader: Mail Header",
            "MDFormMailFooter: Mail Footer",
            // German
            "Language: de",
            "MDFormMandatory: *",
            "MDFormSubmitted: <strong>Formular erfolgreich abgesendet.</strong>",
            "MDFormCSVSaved: Daten erfolgreich gespeichert.",
            "MDFormEmailSent: Daten erfolgreich gesendet.",
            "MDFormMailHeader: Mail Header",
            "MDFormMailFooter: Mail Footer",
        ));
    }

    /**
     * ========================================================================
     * FORM RENDERING & SUBMISSION HANDLING
     * ========================================================================
     * 
     * Main entry point for form element parsing. Called by Yellow CMS when
     * processing [mdform] elements in page content.
     * 
     * Determines whether to render the form or process a submission based on
     * request parameters.
     * 
     * @param object $page Current page object
     * @param string $name Element name (should be "mdform")
     * @param string $text Element arguments (filename and optional dispatch format)
     * @param array $attributes Element attributes
     * @param string $type Element type ("block" or "inline")
     * @return string|null HTML output or null if not an mdform element
     */
    public function onParseContentElement($page, $name, $text, $attributes, $type) {
        $output = null;
        
        // Only process elements named "mdform"
        if ($name == "mdform" && ($type == "block" || $type == "inline")) {
            // Parse arguments: filename and optional dispatch format (e.g., "contact html,csv")
            list($file, $dispatch_format) = $this->yellow->toolbox->getTextArguments($text); 
            $path = $this->yellow->system->get("MDFormDirectory");
            
            // SECURITY: Strip all path components to prevent directory traversal attacks
            // This ensures only the filename is used, blocking paths like "../../../etc/passwd"
            $file = basename(trim($file));
            
            // SECURITY: Verify the resolved path is within the allowed base directory
            $fullPath = realpath($path . $file);
            $basePath = realpath($path);
            
            // Validate path exists and is within base directory
            if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
                $output = "<p><em>[mdform] Error: Form file not found or access denied.</em></p>\n";
                return $output;
            }
            
            // Process form if filename provided
            if (!empty($file)) {
                if (file_exists($fullPath)) {
                    // Check if this is a form submission (POST request with form-status=send)
                    if (($page->getRequest("form-status") === "send") && 
                        ($page->getRequest("mdform-file") === $file)) {
                        
                        // Display success message and process submission
                        $output = $this->yellow->language->getText("MDFormSubmitted") . "<br>\n";
                        $output .= $this->processSend($path, $file, $dispatch_format, $page->getRequest("hash")); 
                    } else {
                        // Render the form for display
                        $output = $this->getForm($path, $file);                      
                    }
                } else {
                    // Form file doesn't exist
                    $output = "<p><em>[mdform] Error: File not found.</em></p>\n";
                }
            }
        }
        return $output;
    }

    /**
     * ========================================================================
     * LOAD MAIN PAGE EXTRA DATA
     * ========================================================================
     * 
     * Load extra data to the main page.
     * Used for adding form customized style sheet CSS
     * Modified from source: GiovanniSalmeri / yellow-table
     * 
     * @param object $page Current page object
     * @param string $name HTML element name 
     * @return array Associative array of field names to metadata
     */
    public function onParsePageExtra($page, $name) {
        $output = null;
        if ($name=="header") {
            $assetLocation = $this->yellow->system->get("coreServerBase").$this->yellow->system->get("coreAssetLocation");
            $style = $this->yellow->system->get("MDFormStyleSheet");
            if ($style != "") $output .= "<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"{$assetLocation}{$style}\" />\n";
        }
        return $output;
    }

    /**
     * ========================================================================
     * FORM LOADING & VALIDATION
     * ========================================================================
     * 
     * Loads and validates a form definition file, then generates HTML output.
     * 
     * @param string $filePath Directory path containing the form file
     * @param string $fileName Name of the form definition file
     * @return string|false HTML form markup or false if invalid
     */
    private function getForm($filePath, $fileName) {
        // Determine file type based on extension
        $fileType = $this->yellow->toolbox->getFileType($filePath.$fileName);
        
        // SECURITY: Only process allowed file extensions
        if (in_array($fileType, $this->yellow->system->get("MDFormAllowedExtensions"))) {
            // Read and parse markdown content into structured form data
            $formData = $this->readMarkdown(file_get_contents($filePath.$fileName));
            // Generate HTML from parsed form structure
            return $this->generateHTMLForm($formData, $fileName);
        }
        return false;
    }

    /**
     * ========================================================================
     * MARKDOWN PARSING
     * ========================================================================
     * 
     * Parses markdown-formatted form definition into a structured array.
     * Supports multiple field types with various syntax patterns.
     * 
     * FIELD SYNTAX PATTERNS:
     * - Text: Label: [Placeholder]
     * - Required: Label*: [Placeholder]
     * - Select: Label: [Placeholder ▼ Option1,Option2]
     * - Radio: Label: [( ) Opt1, ( ) Opt2] or [(x) Opt1, ( ) Opt2]
     * - Checkbox: Label: [[ ] Opt1, [ ] Opt2]
     * - Toggle: Label: FieldName: ON/OFF
     * - Date: Label: [DD/MM/YYYY]
     * - Textarea: Label: [Multi-line...]
     * 
     * @param string $fileContent Raw markdown content from form file
     * @return array Structured form field definitions
     */

    // 1: Logic to read Markdown and return a structured array
    private function readMarkdown($fileContent) {
        $lines = explode("\n", $fileContent);
        $formData = [];
        $counters = ['radio' => 0, 'check' => 0, 'input' => 0];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // =========================================================================
            // FIX: Smart Mandatory Detection
            // =========================================================================
            // Only strip trailing '*' if the line contains a form field definition (brackets)
            // This preserves Markdown italics like "*Contact Form:*"
            $isMandatory = false;
            $cleanLine = $line;

            if (strpos($line, '[') !== false && strpos($line, ']') !== false) {
                // It's a form field line, check for mandatory marker
                if (substr($line, -1) === '*') {
                    $isMandatory = true;
                    $cleanLine = rtrim($line, '*');
                }
            }
            // If no brackets, keep the line exactly as is (preserves *italics*, **bold**, etc.)

            $labelPrefix = ""; 
            $elementBody = $cleanLine;
            
            // Try to extract label and body (for form fields)
            // Note: We use the cleaned line here
            if (preg_match('/^(.+?):\s*(\[.*\].*)$/', $cleanLine, $matches)) {
                $labelPrefix = trim($matches[1]);
                $elementBody = trim($matches[2]);
            }

            $entry = [
                'label' => $labelPrefix,
                'required' => $isMandatory,
                'name' => '',
                'type' => '',
                'options' => [],
                'placeholder' => '',
                'autocomplete' => ''
            ];

            // =========================================================================
            // STEP 1: EXTRACT AUTOCOMPLETE ATTRIBUTE FIRST
            // =========================================================================
            if (preg_match('/^(.*)\{([a-z0-9\-]+)\}\s*$/i', $elementBody, $attrMatches)) {
                $elementBody = trim($attrMatches[1]);
                $entry['autocomplete'] = strtolower(trim($attrMatches[2]));
            }

            // =========================================================================
            // STEP 2: DETECT FIELD TYPE OR MARKDOWN TEXT
            // =========================================================================
            
            // Dropdown
            if (preg_match('/^\[(.*?)\s*▼\s*;\s*(.*)\]$/u', $elementBody, $matches)) {
                $entry['type'] = 'select';
                $entry['name'] = $this->cleanName($labelPrefix ?: "dropdown_" . (++$counters['input']));
                $entry['placeholder'] = trim($matches[1]);
                $entry['options'] = array_map('trim', explode(',', $matches[2]));
            } 
            // Radio
            elseif (preg_match('/^\[(\(\s*[xX ]?\s*\).*?)\]$/', $elementBody, $matches)) {
                $entry['type'] = 'radio';
                $entry['name'] = $this->cleanName($labelPrefix ?: "radio_group_" . (++$counters['radio']));
                $parts = explode(',', $matches[1]);
                foreach ($parts as $p) {
                    $entry['options'][] = trim(preg_replace('/\(\s*[xX ]?\s*\)/', '', $p));
                }
            } 
            // Checkbox
            elseif (preg_match('/^\[(\[\s*[xX ]?\s*\].*?)\]$/', $elementBody, $matches)) {
                $entry['type'] = 'checkbox';
                $entry['name'] = $this->cleanName($labelPrefix ?: "check_group_" . (++$counters['check']));
                $parts = explode(',', $matches[1]);
                foreach ($parts as $p) {
                    $entry['options'][] = trim(preg_replace('/\[\s*[xX ]?\s*\]/', '', $p));
                }
            } 
            // Toggle
            elseif (preg_match('/^\[(.*?):\s*ON\/OFF\]$/i', $elementBody, $matches)) {
                $entry['type'] = 'toggle';
                $entry['name'] = $this->cleanName(trim($matches[1]));
            } 
            // Date
            elseif ($elementBody === "[DD/MM/YYYY]") {
                $entry['type'] = 'date';
                $entry['name'] = $this->cleanName("date_".(++$counters['input']));
            } 
            // Text/Textarea
            elseif (preg_match('/^\[(.*)\]$/', $elementBody, $matches)) {
                $rawPlaceholder = $matches[1];
                $entry['placeholder'] = trim($rawPlaceholder, '. ');
                $entry['name'] = $this->cleanName($labelPrefix ?: $entry['placeholder']);
                $entry['type'] = (strpos($rawPlaceholder, '...') !== false) ? 'textarea' : 'text';
            } 
            // MARKDOWN TEXT (Lines without brackets)
            elseif (empty($labelPrefix) && !preg_match('/^\[.*\]$/', $cleanLine)) {
                // This is a standalone markdown line
                $entry['type'] = 'markdown';
                $entry['content'] = $cleanLine; // Use the original cleanLine (with * preserved)
                $entry['name'] = 'markdown_' . count($formData);
            }

            if ($entry['type']) $formData[] = $entry;
        }
        return $formData;
    }
    /**
     * ========================================================================
     * HTML FORM GENERATION
     * ========================================================================
     * 
     * Converts parsed form structure into HTML markup.
     * Includes CSRF protection token and hidden fields for submission handling.
     * 
     * @param array $formData Structured form field definitions
     * @param string $fileName Original form filename (for submission tracking)
     * @return string Complete HTML form markup
     */
    // 2: Generate HTML output from the structured array
    private function generateHTMLForm($formData, $fileName) {
        $output = "<div class=\"mdform-wrapper\">\n  <form method=\"post\">\n";
        $output .= "    <input type=\"hidden\" name=\"mdform-file\" value=\"".htmlspecialchars($fileName)."\">\n";

        foreach ($formData as $field) {
            // =========================================================================
            // RENDER MARKDOWN TEXT ELEMENTS
            // =========================================================================
            if ($field['type'] === 'markdown') {
                // Parse the markdown content using Yellow's parser
                $parsedContent = $this->parseText($this->yellow->page, $field['content']);
                $output .= "    <div class=\"mdform-markdown\">$parsedContent</div>\n";
                continue;
            }

            // =========================================================================
            // RENDER FORM INPUT ELEMENTS
            // =========================================================================
            $req = $field['required'] ? "required" : "";
            $star = $field['required'] ? $this->yellow->language->getText("MDFormMandatory") : "";

            $output .= "    <div class=\"mdform-group\">\n";
            if ($field['label']) $output .= "      <strong>{$field['label']} $star</strong><br>\n";

            // Determine final HTML type
            $htmlType = $field['type'];
            if ($field['autocomplete'] === 'email' && $field['type'] === 'text') {
                $htmlType = 'email';
            } elseif ($field['autocomplete'] === 'tel' && $field['type'] === 'text') {
                $htmlType = 'tel';
            }

            switch ($field['type']) {
                case 'select':
                    $output .= "      <select name=\"{$field['name']}\" $req";
                    if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    $output .= ">\n";
                    $output .= "        <option value=\"\">{$field['placeholder']}</option>\n";
                    foreach ($field['options'] as $o) $output .= "        <option value=\"$o\">$o</option>\n";
                    $output .= "      </select>\n";
                    break;
                    
                case 'radio':
                    foreach ($field['options'] as $o) {
                        $output .= "      <label><input type=\"radio\" name=\"{$field['name']}\" value=\"$o\" $req";
                        if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                        $output .= "> $o</label> \n";
                    }
                    break;
                    
                case 'checkbox':
                    foreach ($field['options'] as $o) {
                        $output .= "      <label><input type=\"checkbox\" name=\"{$field['name']}[]\" value=\"$o\"";
                        if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                        $output .= "> $o</label> \n";
                    }
                    break;
                    
                case 'toggle':
                    $output .= "      <label class=\"switch\">{$field['name']} <input type=\"checkbox\" name=\"{$field['name']}\" value=\"ON\"";
                    if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    $output .= "></label>\n";
                    break;
                    
                case 'date':
                    $output .= "      <input type=\"date\" name=\"{$field['name']}\" $req";
                    if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    $output .= ">\n";
                    break;
                    
                case 'textarea':
                    $output .= "      <textarea name=\"{$field['name']}\" placeholder=\"{$field['placeholder']}\" $req style=\"width:100%\"";
                    if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    $output .= "></textarea>\n";
                    break;
                    
                case 'text':
                    $output .= "      <input type=\"$htmlType\" name=\"{$field['name']}\" placeholder=\"{$field['placeholder']}\" $req style=\"width:100%\"";
                    if ($field['autocomplete']) $output .= " autocomplete=\"{$field['autocomplete']}\"";
                    $output .= ">\n";
                    break;
            }
            $output .= "    </div>\n";
        }
 
        $output .= "    <input type=\"hidden\" name=\"hash\" value=\"".$this->createHashString($this->yellow->system->get("MDFormHashPasskey"))."\" />\n";  
        $output .= "    <input type=\"hidden\" name=\"referer\" value=\"".$this->yellow->toolbox->getServer("HTTP_REFERER")."\" />\n";
        $output .= "    <input type=\"hidden\" name=\"form-status\" value=\"send\" />\n";        
        $output .= "    <button type=\"submit\">Submit</button>\n  </form>\n</div>\n";
        return $output;
    }
     
    /**
     * ========================================================================
     * MARKDOWN TEXT PARSER
     * ========================================================================
     * 
     * Parses markdown text using Yellow CMS's built-in parser.
     * Used for rendering non-form elements (headings, descriptions, etc.)
     * Source: GiovanniSalmeri / yellow-table
     * 
     * @param object $page Current page object
     * @param string $text Raw markdown text to parse
     * @param bool $singleLine Remove surrounding <p> tags for inline content
     * @return string Parsed HTML output
     */
    private function parseText($page, $text, $singleLine = true) {
        $parser = $this->yellow->extension->get($this->yellow->system->get("parser"));
        $output = $parser->onParseContentRaw($page, $text);
        if ($singleLine) {
            if (substr($output, 0, 3)=="<p>" && substr($output, -5)=="</p>\n") {
                $output = substr($output, 3, -5);
            }
        }
        return $output;       
    }    
    
    /**
     * ========================================================================
     * FORM METADATA EXTRACTION
     * ========================================================================
     * 
     * Extracts form field structure (names and required status) from markdown.
     * Used for processing submissions without re-parsing the entire file.
     * 
     * @param string $fileContent Raw markdown content
     * @return array Associative array of field names to metadata
     */
    /**
     * Extracts ONLY data-holding fields for CSV/Email processing.
     * Excludes 'markdown' type elements which are for display only.
     */
    private function getFormMetadata($fileContent) {
        $formData = $this->readMarkdown($fileContent);
        $form_structure = [];
        
        foreach ($formData as $field) {
            // SKIP markdown elements (headings, descriptions)
            if (!($field['type'] === 'markdown')) {
		    $form_structure[$field['name']] = [
			'name' => $field['name'], 
			'required' => $field['required'],
			'type' => $field['type'] // Keep type if needed later
		    ];
            }
        }
        return $form_structure;
    }
    /**
     * ========================================================================
     * FORM SUBMISSION PROCESSING
     * ========================================================================
     * 
     * Handles POST submission with security validations and dispatch routing.
     * Validates CSRF token, checks rate limits, then routes to appropriate
     * output handlers based on dispatch format.
     * 
     * DISPATCH COMMANDS:
     * - html: Display submitted values on page
     * - csv: Save submission to CSV file
     * - email: Send email notification
     * 
     * @param string $filePath Directory path containing form file
     * @param string $fileName Form definition filename
     * @param string $dispatch_format Comma-separated dispatch commands
     * @param string $hash CSRF token from submission
     * @return string Processing result message
     */
    private function processSend($filePath, $fileName, $dispatch_format, $hash) {
        $output = "";
        # $output = "<p><em>[mdform] Form Status: " .  htmlspecialchars($this->yellow->page->getRequest("form-status")) . "</em></p>\n";
        
        // =========================================================================
        // SECURITY: CSRF TOKEN VALIDATION
        // =========================================================================
        if (!$this->checkHashString($hash, $this->yellow->system->get("MDFormHashPasskey"))) {
            return "<p><em>[mdform] Error: Passkey is not valid. Please reload form and submit again.</em></p>";
        }
        
        // =========================================================================
        // SECURITY: RATE LIMITING CHECK
        // =========================================================================
        if ($this->isRateLimited()) {
            return "<p><em>[mdform] Error: Please wait a moment before submitting again.</em></p>";
        }
        
        // Process dispatch commands if specified
        if (!is_string_empty($dispatch_format)) {
            # $output .= "<p><em>[mdform] Dispatch Format: \"$dispatch_format\" </em></p>\n";
            
            $form_structure = $this->getFormMetadata(file_get_contents($filePath.$fileName));

            // Split dispatch commands by spaces or commas
            $dispatch_commands = preg_split('/[\s,]+/', $dispatch_format, -1, PREG_SPLIT_NO_EMPTY);

            // Execute each dispatch command in order
            foreach ($dispatch_commands as $cmd) {
                if ($cmd === "html") {
                    $output .= $this->sub_dispatch_html($form_structure);
                } elseif ($cmd === "csv") {
                    $output .= $this->sub_dispatch_csv($form_structure, $fileName);
                } elseif ($cmd === "email") {
                    $output .= $this->sub_dispatch_email($form_structure);
                }
            }
        }
        return $output;
    }

    /**
     * ========================================================================
     * UTILITY FUNCTIONS
     * ========================================================================
     * 
     * Cleans field names by replacing special characters with underscores.
     * Ensures valid HTML form field names.
     * 
     * @param string $name Raw field name
     * @return string Sanitized field name
     */
    private function cleanName($name) {
        return str_replace([' ', '.', '[', ']', ':', '*'], '_', trim($name));
    }

    /**
     * ========================================================================
     * CSRF TOKEN GENERATION
     * ========================================================================
     * 
     * Creates time-based hash token for CSRF protection.
     * Token is valid for current hour and previous hour (allows for clock skew).
     * 
     * TOKEN COMPONENTS:
     * - Secret passkey (configurable)
     * - Client IP address (binds token to visitor)
     * - Current hour timestamp (time-limited validity)
     * 
     * @param string $string Secret passkey
     * @return string SHA256 hash token
     */
    public function createHashString($string) {
        $ip = $this->yellow->toolbox->getServer("REMOTE_ADDR");
        $hour = (int)(time()/3600); // Current hour block
        $hash = $this->yellow->toolbox->createHash($string.$ip.$hour, "sha256");
        if (is_string_empty($hash)) $hash = "error-hash-algorithm-sha256";
        return $hash;
    }

    /**
     * ========================================================================
     * CSRF TOKEN VALIDATION
     * ========================================================================
     * 
     * Verifies submitted CSRF token matches expected value.
     * Checks both current and previous hour to account for timing differences.
     * 
     * @param string $hash Submitted token
     * @param string $string Secret passkey
     * @return bool True if token is valid
     */
    public function checkHashString($hash, $string) {
        $ip = $this->yellow->toolbox->getServer("REMOTE_ADDR");
        $currentHour = (int)(time()/3600);
        $previousHour = $currentHour - 1;

        // Check current hour AND previous hour for timing tolerance
        return $this->yellow->toolbox->verifyHash($string.$ip.$currentHour, "sha256", $hash) || 
               $this->yellow->toolbox->verifyHash($string.$ip.$previousHour, "sha256", $hash);
    }

    /**
     * ========================================================================
     * RATE LIMITING
     * ========================================================================
     * 
     * Prevents spam by limiting form submissions per IP address.
     * Uses file-based storage for submission timestamps.
     * 
     * LIMIT CONFIGURATION:
     * - Maximum: 1 submission per 10 seconds per IP
     * - Cleanup: Old entries removed after 1 hour
     * 
     * @return bool True if submission is rate limited
     */
    private function isRateLimited() {
        $limitDir = $this->yellow->system->get("MDFormRateLimitDirectory");
        $ip = $this->yellow->toolbox->getServer("REMOTE_ADDR");
        
        // Create unique fingerprint combining IP + User-Agent + Session
        $userAgent = $this->yellow->toolbox->getServer("HTTP_USER_AGENT") ?? '';
        $sessionId = session_id() ?? '';
        $fingerprint = $ip . '|' . $userAgent . '|' . $sessionId;
        $fingerprintHash = hash("sha256", $fingerprint);
        
        $file = $limitDir . $fingerprintHash;
        
        // Create rate limit directory if it doesn't exist (secure permissions)
        if (!is_dir($limitDir)) {
            mkdir($limitDir, 0700, true); // SECURE: Restrictive permissions
        }
        
        // Clean old rate limit entries (older than 1 hour)
        $this->cleanupOldRateLimitFiles($limitDir);
        
        // Check if IP has submitted recently
        if (file_exists($file)) {
            $lastSubmission = (int)file_get_contents($file);
            $waitTime = 10; // Seconds between submissions
            
            if ((time() - $lastSubmission) < $waitTime) {
                return true; // Rate limited
            }
        }
        
        // Record current submission timestamp
        file_put_contents($file, time(), LOCK_EX);
        return false;
    }

    /**
     * ========================================================================
     * RATE LIMIT CLEANUP
     * ========================================================================
     * 
     * Removes expired rate limit files to prevent disk space issues.
     * 
     * @param string $dir Directory containing rate limit files
     */
    private function cleanupOldRateLimitFiles($dir) {
        $maxAge = 3600; // 1 hour in seconds
        
        if (!is_dir($dir)) return;
        
        foreach (glob($dir . '*') as $file) {
            if (is_file($file) && (time() - filemtime($file) > $maxAge)) {
                @unlink($file); // Suppress errors for race conditions
            }
        }
    }

    /**
     * ========================================================================
     * DISPATCH: HTML OUTPUT
     * ========================================================================
     * 
     * Displays submitted form values on the page after successful submission.
     * Useful for confirmation pages or debugging.
     * 
     * @param array $form_structure Form field metadata
     * @return string HTML display of submitted values
     */
    private function sub_dispatch_html($form_structure) {
        $output = ""; 
        foreach (array_keys($form_structure) as $header) {
            $val = $this->yellow->page->getRequest($header);
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $output .= htmlspecialchars($header) ." => " . htmlspecialchars(trim($displayVal)) . "<br>\n";
        }
        return $output;
    }

    /**
     * ========================================================================
     * DISPATCH: CSV EXPORT
     * ========================================================================
     * 
     * Appends form submission to CSV file for data export.
     * Automatically creates headers on first submission.
     * Backs up file if column structure changes.
     * 
     * SECURITY NOTES:
     * - CSV files stored in configured output directory
     * - File permissions should be restricted (not web-accessible ideally)
     * - Consider adding authentication for CSV download
     * 
     * @param array $form_structure Form field metadata
     * @param string $fileName Form definition filename (used for CSV filename)
     * @return string Success/error message
     */
    private function sub_dispatch_csv($form_structure, $fileName)  {
        $output = ""; 
        $delimiter = ",";
        $enclosure = '"'; // Double quotes for enclosing fields
        $escape = "\\";   // Escape character
        
        $csvPath = $this->yellow->system->get("MDFormDirectoryCSVOutput") . $fileName . ".csv"; 
        $expectedHeaders = array_keys($form_structure);

        // Ensure directory exists
        $csvDir = dirname($csvPath);
        if (!is_dir($csvDir)) {
            mkdir($csvDir, 0755, true);
        }

        // Check existing file for header consistency
        if (file_exists($csvPath)) {
            $handle = fopen($csvPath, 'r');
            $existingHeader = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
            fclose($handle);
            
            // Backup file if headers don't match (form structure changed)
            if ($existingHeader !== $expectedHeaders) {
                rename($csvPath, $csvPath . "." . date("YmdHis") . ".bak");
            }
        }

        // Prepare data row
        $dataRow = [];
        foreach ($expectedHeaders as $header) {
            $val = $this->yellow->page->getRequest($header);
            
            // Handle arrays (checkboxes) by joining with semicolon or comma
            // Using semicolon to avoid confusion if the user entered commas
            if (is_array($val)) {
                $val = implode("; ", $val); 
            }
            
            // Ensure value is a string
            $dataRow[] = is_string($val) ? $val : (string)$val;
        }

        // Open file for appending
        $isNew = !file_exists($csvPath);
        $handle = fopen($csvPath, 'a');
        
        if ($handle === false) {
            return "<p><em>[mdform] Error: Cannot open CSV file for writing.</em></p>";
        }

        // Write headers if new file
        if ($isNew) {
            fputcsv($handle, $expectedHeaders, $delimiter, $enclosure, $escape);
        }

        // Write data row
        // fputcsv automatically quotes fields containing delimiters, newlines, or quotes
        fputcsv($handle, $dataRow, $delimiter, $enclosure, $escape);
        
        fclose($handle);
        
        $output = "Success! Data saved.<br>\n"; 
        return $output;
    }

    /**
     * ========================================================================
     * DISPATCH: EMAIL NOTIFICATION
     * ========================================================================
     * 
     * Sends email notification with form submission data.
     * Includes comprehensive security sanitization to prevent header injection.
     * 
     * SECURITY MEASURES:
     * - Email address validation
     * - Header injection prevention (CRLF removal)
     * - Subject line length limiting
     * - Referer URL sanitization
     * 
     * @param array $form_structure Form field metadata
     * @return string Success/error message
     */
    private function sub_dispatch_email($form_structure) {
        $output = "";
        $message = "";
        
        // Build email body from form data
        foreach (array_keys($form_structure) as $header) {
            $val = $this->yellow->page->getRequest($header);
            $displayVal = is_array($val) ? implode(', ', $val) : $val;
            $message .= htmlspecialchars($header) . " => " . htmlspecialchars(trim($displayVal)) . "\r\n";
        }
        
        // Get submission metadata
        $hash = trim($this->yellow->page->getRequest("hash"));
        $referer = trim($this->yellow->page->getRequest("referer"));
        
        // Get system configuration
        $sitename = $this->yellow->system->get("sitename");
        $siteEmail = $this->yellow->system->get("MDFormEmail");
        $subject = $this->yellow->page->get("title") . " - " . $sitename;
        
        // Determine sender information
        $userName = $this->yellow->system->get("author");
        $userEmail = $this->yellow->system->get("email");
        
        // Get email template parts
        $header = $this->yellow->language->getText("MDFormMailHeader");
        $header = str_replace("\\n", "\r\n", $header);
        $footer = $this->yellow->language->getText("MDFormMailFooter");
        $footer = str_replace("\\n", "\r\n", $footer);
        
        // Allow page-level author/email if not restricted
        if ($this->yellow->page->isExisting("author") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $userName = $this->yellow->page->get("author");
        }
        if ($this->yellow->page->isExisting("email") && !$this->yellow->system->get("MDFormEmailRestriction")) {
            $userEmail = $this->yellow->page->get("email");
        }
        
        // =========================================================================
        // SECURITY: EMAIL HEADER INJECTION PREVENTION
        // =========================================================================
        $userName = $this->sanitizeEmailName($userName);
        $userEmail = $this->sanitizeEmailAddress($userEmail);
        $sitename = $this->sanitizeEmailName($sitename);
        
        // Validate email format
        if (is_string_empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $output = "<p><em>[mdform] Error: Email address settings not valid.</em></p>";
            return $output;
        }
        
        // Sanitize referer URL
        $referer = filter_var($referer, FILTER_SANITIZE_URL);
        
        // Build email headers   
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
        
        // Build email message and send 
        $mailMessage = "$header\r\n\r\n$message\r\n-- \r\n$footer";
        $output = $this->yellow->toolbox->mail("MDForm", $mailHeaders, $mailMessage) 
            ? ($this->yellow->language->getText("MDFormEmailSent") . "<br>\n")
            : "<p><em>[mdform] Error: Email not sent</em></p>";
        
        return $output;
    }

    // SECURE: Remove CRLF and special characters from names
    private function sanitizeEmailName($name) {
        // Remove newlines, carriage returns, and null bytes
        $name = preg_replace('/[\r\n\x00]/', '', $name);
        // Remove angle brackets and other dangerous characters
        $name = str_replace(['<', '>', ',', ';'], '', $name);
        return trim($name);
    }

    // SECURE: Validate and clean email addresses
    private function sanitizeEmailAddress($email) {
        $email = preg_replace('/[\r\n\x00]/', '', $email);
        $email = trim($email);
        return $email;
    }

    // SECURE: Sanitize email subject line
    private function sanitizeEmailSubject($subject) {
        $subject = preg_replace('/[\r\n\x00]/', '', $subject);
        $subject = trim($subject);
        // Limit length to prevent buffer issues
        return mb_substr($subject, 0, 255, 'UTF-8');
    }
    
}

