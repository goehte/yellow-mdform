# MDForm
*Status 0.0.7 alpha (experimental)*

**MDForm** is an extension for [Datenstrom Yellow](https://datenstrom.se/yellow/) that allows you to create HTML forms using simple Markdown-formatted files.  
It provides a secure way to collect data, supporting multiple input types and dispatch methods such as email notifications and CSV logging.

[Learn more about Yellow CMS extensions](https://github.com/annaesvensson/yellow-update).

## Screenshot:
<p align="center"><img src="MDForm_Screenshot.png" alt="Screenshot" /></p>

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
### A Simple Contact Form
Full Name: [Your First and Last Name]{name}*
Email: [your@email.com]{email}*
Birthday: [DD/MM/YYYY;1900-01-01..TODAY]
Message: [Your message to us.....]
Newsletter: [Subscribe to newsletter: OFF/ON]
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

## Credits
Special thanks to:
* Giovanni Salmeri for the extension: [Yellow Table](https://github.com/GiovanniSalmeri/yellow-table)
* Anna Svensson for the extension: [Yellow Contact](https://github.com/annaesvensson/yellow-contact/)

Your extensions have been the main inspiration and learning resource for this extension. Thank you for sharing your knowledge with the Yellow CMS community!

## This is Expiremental Alpha Software
Use in production at your own risk. Back up your data regularly and test thoroughly before deploying to production environments.

**Made with 💛 for the Yellow CMS Community**
