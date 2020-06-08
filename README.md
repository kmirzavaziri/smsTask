# sms task
This project is just a simple mock API for sending sms with custom body to custom phone number, while logging and reporting some useful information.

We use following APIs to send sms, this project contains a simple code for each, which responses successful message or failure message randomly (Does not send the sms really). the first API (`port 81`) fails 16% of times and the second (`port 82`) fails 7% of times.

```
localhost:81/sms/send/?number={PHONE_NUMBER}&body={MESSAGE_BODY}
localhost:82/sms/send/?number={PHONE_NUMBER}&body={MESSAGE_BODY}
```

One can find the related these two simple codes in folders [API1](APIs/1/) and [API1](APIs/2/). 

## Dependencies
We use Symfony Routing Component which can be installed using
```
$ composer require symfony/routing
```

## Usage
Call it using the following

```
localhost:80/sms/send/?number={PHONE_NUMBER}&body={MESSAGE_BODY}
```

