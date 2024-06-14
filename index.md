# SimpleServerHealth

Dead simple PHP server health API endpoint. No dependencies, customizable, lightweight, fast, powerful. Pick all five. 

## What you need to know

Let's get started with the basics!

### How to install

Drop the `index.php` file onto your web server.

### What you need

You need PHP 7.4 or higher installed.

### What you get

A single endpoint that returns a JSON response with the server health.

### What you can do

You can also configure basic token authentication and configure what data is shown.

## Security

Remember that health checks may expose sensitive information about your server configuration.

Make sure to secure the endpoint properly, or take care to limit the information shown to the public.

### Enabling token authentication

We provide a **very basic** token authentication mechanism. Remember, by default anyone can access the endpoint.

To enable authentication, either set an `APP_AUTH_TOKEN` environment variable or edit the `index.php` file and set the `Config::APP_AUTH_TOKEN` constant.

In both cases, this constant should be the `sha256` hash of the token you want to use.

The token can then be passed either as a `Bearer` token in the `Authorization` header or as a `token` query parameter.

## License

The MIT License
