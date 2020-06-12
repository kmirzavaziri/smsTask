# sms task
This project is just a simple mock API for sending sms with custom body to custom phone number, while logging and reporting some useful information.

We use following APIs to send sms, this project contains a simple code for each, which responses successful message or failure message randomly (Does not send the sms really). the first API (`port 81`) fails 63% of times and the second (`port 82`) fails 77% of times.

```
localhost:81/sms/send/?number={PHONE_NUMBER}&body={MESSAGE_BODY}
localhost:82/sms/send/?number={PHONE_NUMBER}&body={MESSAGE_BODY}
```

One can find the related these two simple codes in folders [API1](APIs/1/) and [API2](APIs/2/). 

## Dependencies
We use Symfony Routing Component for routes (we could set up a simple `.htaccess` but this way seems a little cleaner), which can be installed using

```
$ composer require symfony/routing
$ composer require symfony/config
$ composer require symfony/http-foundation
$ composer require symfony/yaml
```

We also use predis for storing unsent messages in a queue to send them by specific time, which can be installed using

```
$ composer require predis/predis
```

## Installation
First go to the following link

```
localhost:80
```

If the app is installed, you'll see a link to report page

```
localhost:80/sms/report
```

and a link to uninstall page

```
localhost:80/sms/uninstall
```

Otherwise, you'll see a link to installation page

```
localhost:80/sms/install
```

## Usage
One can call the API using the following

```
localhost:80/sms/send/?number={PHONE_NUMBER}&body={MESSAGE_BODY}
```

and visit the report page by

```
localhost:80/sms/report
```

Also you may send request to the following link in specific periods of time to ask the api to send unsent messages.

```
localhost:80/sms/clear_queue
```

One can find the log files in the log folder.

## Test
One can send random requests to api using

```
localhost:80/sms/test
```

This will generate 20 different number and send requests with each of numbers, 5 to 30 times (randomly), each with random body.