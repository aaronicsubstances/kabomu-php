# Kabomu Library for PHP

This is a port of the Kabomu library originally written in C#.NET to PHP 8.1.30 and above (first tested with PHP CLI 8.3.6 and Ubuntu 24.04.1 LTS).

In a nutshell, Kabomu enables building quasi web applications that can connect endpoints within localhost and even within an OS process, through IPC mechanisms other than TCP.

See the [repository for the .NET version](https://github.com/aaronicsubstances/cskabomu) for more details.

## Install

```
composer require aaronicsubstances/kabomu
```

## Usage

The entry classes of the libary are [StandardQuasiHttpClient](https://github.com/aaronicsubstances/kabomu-php/blob/main/src/StandardQuasiHttpClient.php) and [StandardQuasiHttpServer](https://github.com/aaronicsubstances/kabomu-php/blob/main/src/StandardQuasiHttpServer.php).

See [Examples](https://github.com/aaronicsubstances/kabomu-php/tree/main/examples) folder for sample file serving programs. Each of those programs demonstrates an IPC mechanism as represented by main files named with "-client" or "-server" suffix. E.g. to run the TCP client example, run

```
php tcp-client.php
```

The sample programs come in pairs: a client program and corresponding server program. The server program must be started first. By default a client program uploads all files from a *logs/client* folder in the current directory, to a folder created in a *logs/server* folder of the server program's current directory.

The [.env-example](https://github.com/aaronicsubstances/kabomu-php/blob/main/examples/.env-example) config file indicates how to change the default client and server endpoints (TCP ports or Unix domain socket paths), as well as the directories of upload and saving.