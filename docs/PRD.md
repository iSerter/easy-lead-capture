A basic -but beautiful- lead capture system written with PHP, Sqlite3, Tailwindcss, HTML/CSS.

The core value proposition is the ease of use. It provides beautiful embeddable lead forms and beautiful after-submission CTAs to invite users to connect on social media. Also the entire package must be lightweight and fast.

It must integrate ReCaptcha v3 too. (configurable to enable/disable).

It must provide a configurable beautiful lead form. (will be embedded to other websites with an iframe or maybe javascript code).

It must store the lead information into an Sqlite3 database. enable WAL mode (Write-Ahead Logging). 

Then it must email to the configured admin email.

To be more clear, here is how I want developers to use this library/app.

## 1. Install via Composer

`composer require iserter/easy-lead-capture`

## 2. Setup / Configure

lead-capture.php
```
// composer autoload

$config = [
    'admin' => [
        'password' =>  env('ADMIN_PASSWORD'), // if empty, the admin panel will be disabled. If valid md5, then admin panel login will work according to it.
        'email' => 'example@example.com', // optional
        'linkedin_url' => 'https://www.linkedin.com/in/iserter', //optional
        'x_url' => 'https://x.com/iSerter', // optional
    ],
    'form' => [
        'logo_url' => '', //optional. If set, it will be shown on top of the form.
        'headline' => '', // optional
        'intro_text' => '', // optional
        'fields' => [
            'name' => ['label' => 'Name', 'required' => true],
            'email' => ['label' => 'E-mail', 'required' => true],
            'phone' => ['label' => 'Phone', 'required' => false],
            'website' => ['label' => 'Website', 'required' => false],
            'message' => ['label' => 'Message', 'required' => false],
            'interests' => [
                'field_type' => 'multi_select' 
                'label' => 'Products',
                'required' => '',
                'options' => [
                    'Product 1', 'Product 2', 'Product 3'
                ] 
            ];
        ], 
        'colors' => [
            // some props to override the default colors. Optional.
        ]
    ],
    'on_submit' => [
        'success_headline' => 'Thank you!'
        'success_message' => 'Thank you for joining the waitlist. We will get back to you when we are launching.', 
        'social_links' => [
            'enabled' => true, // true by default anyway. (if the links are set)
            'message' => 'Connect with us on social media to make sure you don't miss updates such as the discount code we will send you upon launch.',
        ], 
    ],
    'captcha' => [
        'enabled' => true, // true or false.
        'provider' => 'recaptcha',
        'recaptcha' => [
            'version' => 3,
            'site_key' => '...',
            'secret_key' => '...',
        ]
    ],
    'mail' => [
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'name' => env('MAIL_FROM_NAME', 'Example'),
        ],
        'mailer' => 'smtp' // options: smtp, sendmail, ses
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ]
    ]
];

$app = new Iserter\EasyLeadCapture\App($config);


$app->run();

```


## 3. Embed front-end form

I don't know what is the best way to embed the lead forms into websites, especially considering the captcha functionality, so please guide me.

I can think of two potential ways: 

iframe:
```
<iframe src="lead-capture.php?function=form">
```

javascript: 

```
<script src="/easy-lead-capture.js" />
<script>
easyLeadCapture.configure({...});
easyLeadCapture.renderForm('#modalId')
</script>
```

Determine the best architecture and decide.


### 4. admins can view their leads and export as CSV.

we need a basic admin panel to view the captured leads and export them as CSV file.

--- 


## Admin

### Architecture Notes.

Should we use https://github.com/slimphp/Slim ? It may not be necessary for such a small project, but it's up to you to decide.

I know we will need SMTP/SES, etc mailer providers, maybe a popular PHP recaptcha library too. 

The front-end form must be beautiful and include front-end validation while keeping the payload size as small as possible.

SQLite3 database allows easy setup of this library/framework/app. I want to provide excellent developer experience. 

It would be nice if the package provides a command to publish frontend assets.