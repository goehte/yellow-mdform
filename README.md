# MDForm
*Status 0.0.6 alpha (experimental)*

**MDForm** is an extension for [Datenstrom Yellow](https://datenstrom.se/yellow/) that allows you to create HTML forms using simple Markdown-formatted files.  
It provides a secure way to collect data, supporting multiple input types and dispatch methods such as email notifications and CSV logging.

[Learn more about Yellow CMS extensions](https://github.com/annaesvensson/yellow-update).

## Description

This extension parses specific Markdown syntax within `.mdf` or `.md` files to generate functional web forms. 
The extension includes built-in security features like CSRF protection and rate limiting to prevent spam and abuse.


## Installation

### Requirements
* Datenstrom Yellow CMS
* PHP 7.4 or higher
* Write permissions to system/workers/ folder

### Installation of experimental alpha version
1.  Download the extension files.
2.  Copy `mdform.php` to your `system/extensions` directory.
3.  Copy `mdform.css` to your `media/assets` directory.
4.  Create a folder named `media/forms/` to store your form definitions.

### Example installation path
```
your-site/
├── system/
│   └── workers/
│       └── mdform.php    ← Place the file here
├── media/
│   ├── forms/            ← Create this folder for form definitions
│   └── tables/           ← CSV output will be stored here
└── index.php
```

*It is recommended to change `MDFormHashPasskey` in the configuration to an individual salt random string to secure your forms against CSRF attacks.*

## Configuration

You can customize the extension behavior in your `system/config/config.ini` file using the following settings:

| Setting | Default Value | Description |
| --- | --- | --- |
| `MDFormDirectory` | `media/forms/` | Where form definition files are stored. |
| `MDFormDirectoryCSVOutput` | `media/tables/` | Where CSV data is saved. |
| `MDFormEmail` | `noreply@server.com` | Default sender email address. |


## Usage

### 1. Create a Form File
Create a file (e.g., `contact.mdf`) in `media/forms/`. Use the following syntax:

```markdown
Name: [Enter your name]*
E-Mail: [email]{email}*
Interests: [[x] Sports, [ ] Music, [x] Reading]
Newsletter: [OFF/ON]
Message: [... Write your message]*
```

*   `*` denotes a mandatory field.
*   `{email}` defines the HTML5 autocomplete attribute.
*   `[ON/OFF]` creates a preselected toggle switch. Use `[OFF/ON]` to make it prechecked.

### 2. Embed the Form
Add the form to any Yellow page using the `[mdform]` shortcut:

`[mdform filename dispatch]`

**Example:**
`[mdform contact.mdf email,csv,html]` or `[mdform contact.mdf "email csv html"]`

This will display the form from `contact.mdf`, send an email, save the result to a CSV, and show a confirmation on the page.
Just remove the options you not would like to use.

## Customization

You can style the form by editing `media/assets/mdform.css`. The extension wraps all elements in a `.mdform-container` class and uses `.mdform-group` for individual fields for easy targeting.

## Support

For bug reports or feature requests, please visit the [GitHub repository](https://github.com/goehte/yellow-mdform/).

## License
GNU GENERAL PUBLIC LICENSE - Feel free to use, modify, and distribute.
Markdown Form Extension for [Datenstrom Yellow CMS](https://github.com/datenstrom/yellow)

## Screenshot:
<p align="center"><img src="MDForm_Screenshot.png" alt="Screenshot" /></p>


---



### Main Idea of this Extension
My primary goal was to create a tool that could generate customised web forms within the Yellow CMS environment, save form data directly to a CSV file, and send an email containing the provided form data. 
The MDForm extension provides a simple, file-based approach: you define your form structure in plain text files (.mdf files), and the extension handles everything from HTML generation to data submission and storage.
MDForm is an excellent alternative to Google Forms for Yellow CMS pages, as it keeps your data on your own server while maintaining simplicity and flexibility.

Related Discussion:
github.com/datenstrom/community/discussions/1028

### Features

* Markdown-Based Form Definition: Define forms in simple .mdf text files
* Multiple Field Types Supported: Text, textarea, email, tel, select, radio, checkbox, toggle, date
* Smart Autocomplete: Automatic autocomplete attributes for better UX (email, tel, address, etc.)
* Multiple Output Methods: HTML display, CSV export, email notifications
* Built-in Base Security: CSRF protection, rate limiting, email header sanitization
* Markdown Content Support: Add headings, descriptions, and formatted text within forms
* Multi-language Ready: English and German language support included
* Zero Database Required: Data stored in CSV files or sent via email

---

### Markdown Form Syntax & Supported Field Types (.mdf file)
**Text Input (single line):**
```
Label: [Placeholder text]
Label*: [Required field]*
Email Field (with autocomplete)
Email: [Enter your email]{email}*
Phone Field (with autocomplete)
Phone: [123-456-789]{tel}*
```
**Textarea (multi line):**
```
Message: [Tell us more...]
```
**Number (multiline):**
```
Number of participants: [1;1..5]
```
**Dropdown Select:**
```
Country: [Select country ▼ Germany,France,Spain,Italy]
```
**Radio Buttons:**
```
Gender: [( ) Male, ( ) Female, ( ) Other]
```
**Checkboxes:**
```
Interests: [[ ] Sports, [ ] Music, [ ] Travel, [ ] Reading]
```
**Toggle Switch:**
```
Newsletter: [ON/OFF]
```
**Date Picker:**
```
Birthday: [DD/MM/YYYY]
```
**Markdown Text (Headings, Descriptions):**
```
*Section Title:*
This is a **description** with *formatting*.
```

## Element Usage - Dispatch Options
Control what happens when the form is submitted:
[mdform contact]                    # Just display form
[mdform contact html]               # Display form + show submitted values
[mdform contact csv]                # Display form + save to CSV file
[mdform contact email]              # Display form + send email notification
[mdform contact "html, csv, email"]     # All three methods combined


## Known Issues & Limitations (Alpha Version)
As this is version 0.0.x-alpha, please be aware of the following:

* Rate limiting uses file-based storage (may need optimization for high traffic)
* CSV file backup on header mismatch creates timestamped backups (ensure disk space)
* Email sending depends on server mail configuration
* No built-in CAPTCHA (consider server-side protection for public forms)
* Limited styling (add custom CSS to match your theme)


## Troubleshooting
### Form Not Appearing

Check file exists in media/forms/[formname].mdf
Verify file extension is .mdf, .fmd, .md, or .form
Check file permissions (readable by web server)

### Email Not Sending

Verify MDFormEmail is set correctly in configuration
Check server mail configuration (SMTP, sendmail, etc.)
Review error logs for mail function failures

### CSV Not Being Created

Ensure media/tables/ folder exists
Check write permissions on the folder
Verify MDFormDirectoryCSVOutput setting is correct

### Rate Limiting Too Strict

Edit isRateLimited() method to adjust $waitTime
Or increase timeout value in the code (actual time between submit a form form one IP address is 10s)


## Credits
Special thanks to:
* Giovanni Salmeri for the extension: [Yellow Table](https://github.com/GiovanniSalmeri/yellow-table)
* Anna Svensson for the extension: [Yellow Contact](https://github.com/annaesvensson/yellow-contact/)

Your extensions have been the main inspiration and learning resource for this extension. Thank you for sharing your knowledge with the Yellow CMS community!


## Ideas for improvments:
*Note: No future enhancements planned.*  

 * E-Mail confirmation of form data
 * Encypted file storage option (storage format TBD)
 * File upload support for images: I suggest to use the extension [Yellow Dropzone](https://github.com/GiovanniSalmeri/yellow-dropzone)

For those who are new to the community, here are some tips and tricks for using the Yellow CMS API:
https://github.com/datenstrom/community/discussions/760

## This is Expiremental Alpha Software
Use in production at your own risk. Back up your data regularly and test thoroughly before deploying to production environments.

**Made with 💛 for the Yellow CMS Community**
