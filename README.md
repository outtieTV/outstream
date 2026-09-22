# outstream
Outstream is an attempt at an nginx web server with rtmp and vod modules. Now with vods and clips!<br />
<br />
<img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/311ed9af-44a0-43c9-a439-e19cd62dd4c7" />
<img width="1920" height="1080" alt="image-1" src="https://github.com/user-attachments/assets/e3335145-52ba-48ea-b63e-447209c940e2" />
<img width="1920" height="1080" alt="067D4EBA-9172-4944-89B6-FE351FC94385" src="https://github.com/user-attachments/assets/8d092faa-3902-41fa-8e4e-d85a32701ac6" />
<br />
Features:
- rtmp livestreaming via OBS to http
- vods
- clips
- autoconverstion from flv to mp4
- vod and clip metadata editing
- clip trimming
- download vods and clips with ease
- basic streamkey authentication (edit auth.json to use)<br />
<br /><br />

# Self-Hosted Nginx Streaming Server Guide

A practical guide to building, configuring, operating, and maintaining a self-hosted livestreaming platform using **Nginx**, **nginx-rtmp-module**, **Kaltura nginx-vod-module**, **PHP-FPM**, **FFmpeg**, and **OBS Studio**.

The goal of this guide is not simply to provide a list of commands. Instead, it explains **how the streaming system works, why each component is used, how the components interact, and how to maintain the server after it has been deployed**.

---

# Table of Contents

* [Introduction](#introduction)

  * [What This Guide Builds](#what-this-guide-builds)
  * [Who This Guide Is For](#who-this-guide-is-for)
  * [Example Architecture](#example-architecture)
* [Chapter 1: Understanding the Streaming Stack](#chapter-1-understanding-the-streaming-stack)

  * [Nginx](#nginx)
  * [RTMP](#rtmp)
  * [HLS](#hls)
  * [VOD](#vod)
  * [PHP-FPM](#php-fpm)
  * [FFmpeg](#ffmpeg)
  * [OBS Studio](#obs-studio)
* [Chapter 2: Planning the Server](#chapter-2-planning-the-server)

  * [Hardware](#hardware)
  * [Network Requirements](#network-requirements)
  * [Storage](#storage)
  * [Directory Layout](#directory-layout)
  * [Ports](#ports)
* [Chapter 3: Preparing the Operating System](#chapter-3-preparing-the-operating-system)

  * [Install Dependencies](#install-dependencies)
  * [Install OBS and VLC](#install-obs-and-vlc)
* [Chapter 4: Building Nginx](#chapter-4-building-nginx)

  * [Create a Source Directory](#create-a-source-directory)
  * [Download Nginx](#download-nginx)
  * [Download the Streaming Modules](#download-the-streaming-modules)
  * [Configure Nginx](#configure-nginx)
  * [Compile and Install](#compile-and-install)
  * [Verify the Build](#verify-the-build)
* [Chapter 5: Configuring PHP-FPM](#chapter-5-configuring-php-fpm)

  * [Install PHP-FPM](#install-php-fpm)
  * [Determine the PHP Version](#determine-the-php-version)
  * [Why PHP-FPM Is Used](#why-php-fpm-is-used)
* [Chapter 6: Configuring Nginx](#chapter-6-configuring-nginx)

  * [Install the Configuration](#install-the-configuration)
  * [Review the Configuration](#review-the-configuration)
  * [Test the Configuration](#test-the-configuration)
* [Chapter 7: Setting Up Web and Media Storage](#chapter-7-setting-up-web-and-media-storage)

  * [Create the Directories](#create-the-directories)
  * [Directory Permissions](#directory-permissions)
  * [Install the Web Interface](#install-the-web-interface)
* [Chapter 8: Understanding RTMP Authentication](#chapter-8-understanding-rtmp-authentication)

  * [Why Authentication Matters](#why-authentication-matters)
  * [The Authentication Process](#the-authentication-process)
  * [Stream Identities](#stream-identities)
  * [Protecting Stream Keys](#protecting-stream-keys)
* [Chapter 9: Configuring OBS Studio](#chapter-9-configuring-obs-studio)

  * [Create an RTMP Connection](#create-an-rtmp-connection)
  * [Configure the Stream Key](#configure-the-stream-key)
  * [Create an OBS Scene](#create-an-obs-scene)
  * [Starting a Stream](#starting-a-stream)
* [Chapter 10: Live Streaming and HLS](#chapter-10-live-streaming-and-hls)

  * [The Live Streaming Pipeline](#the-live-streaming-pipeline)
  * [HLS Output](#hls-output)
  * [Watching the Stream](#watching-the-stream)
* [Chapter 11: Video on Demand](#chapter-11-video-on-demand)

  * [What Is VOD](#what-is-vod)
  * [VOD Storage](#vod-storage)
  * [VOD Playback](#vod-playback)
* [Chapter 12: Clips](#chapter-12-clips)

  * [Clip Storage](#clip-storage)
  * [Clip Workflow](#clip-workflow)
* [Chapter 13: Operating the Server](#chapter-13-operating-the-server)

  * [Starting Nginx](#starting-nginx)
  * [Reloading Configuration](#reloading-configuration)
  * [Checking Service Status](#checking-service-status)
  * [Monitoring Logs](#monitoring-logs)
* [Chapter 14: Testing and Troubleshooting](#chapter-14-testing-and-troubleshooting)

  * [Testing Nginx](#testing-nginx)
  * [Testing the Web Server](#testing-the-web-server)
  * [Testing RTMP](#testing-rtmp)
  * [Testing HLS](#testing-hls)
  * [Testing PHP](#testing-php)
  * [Testing VOD](#testing-vod)
  * [Common Problems](#common-problems)
* [Chapter 15: Security and Maintenance](#chapter-15-security-and-maintenance)

  * [Stream Authentication](#stream-authentication)
  * [HTTPS](#https)
  * [Firewall Configuration](#firewall-configuration)
  * [File Permissions](#file-permissions)
  * [Storage Management](#storage-management)
  * [Logging and Monitoring](#logging-and-monitoring)
* [Chapter 16: Expanding the Platform](#chapter-16-expanding-the-platform)

  * [Multiple Streamers](#multiple-streamers)
  * [Automatic Recording](#automatic-recording)
  * [Automated Clips](#automated-clips)
  * [User Accounts](#user-accounts)
  * [Monitoring](#monitoring)
* [Appendix A: Useful Commands](#appendix-a-useful-commands)
* [Appendix B: Example Directory Structure](#appendix-b-example-directory-structure)
* [Appendix C: Glossary](#appendix-c-glossary)

---

# Introduction

## What This Guide Builds

This guide describes a self-hosted streaming server capable of handling several related tasks:

* Receiving live streams from OBS Studio.
* Authenticating stream publishers.
* Accepting RTMP connections.
* Generating HLS live streams.
* Serving a browser-based video player.
* Hosting recorded VOD content.
* Hosting video clips.
* Running PHP-based web applications.
* Providing a foundation for a personal streaming platform.

Instead of relying entirely on a third-party streaming service, the server handles the core streaming infrastructure itself.

A simplified workflow looks like this:

```text
OBS Studio
    │
    │ RTMP
    ▼
Nginx
    │
    ├── Authentication
    │
    ├── HLS
    │
    ├── VOD
    │
    └── Clips
         │
         ▼
    Web Interface
```

---

## Who This Guide Is For

This guide is intended for people who want to operate their own streaming infrastructure and are comfortable working with:

* Linux
* SSH
* The command line
* Nginx configuration
* Basic networking
* OBS Studio
* File permissions
* PHP/PHP-FPM

You do not need to be an expert in video streaming before starting, but understanding the concepts in the early chapters will make troubleshooting considerably easier.

---

## Example Architecture

The complete system can be thought of as several layers.

```text
                     ┌──────────────────┐
                     │    OBS Studio    │
                     └────────┬─────────┘
                              │
                              │ RTMP
                              ▼
                    ┌─────────────────────┐
                    │       Nginx         │
                    │                     │
                    │ nginx-rtmp-module   │
                    │ nginx-vod-module    │
                    └──────┬──────┬───────┘
                           │      │
                     HLS   │      │   VOD
                           │      │
                           ▼      ▼
                      ┌──────┐  ┌──────┐
                      │ HLS  │  │ VOD  │
                      └──┬───┘  └──┬───┘
                         │         │
                         └────┬────┘
                              ▼
                     ┌─────────────────┐
                     │   Web Player    │
                     │    PHP/HTML     │
                     └─────────────────┘
```

Authentication sits alongside the streaming pipeline:

```text
OBS
 │
 │ Publish
 ▼
Nginx
 │
 │ on_publish
 ▼
auth.php
 │
 ├── Accepted → Stream continues
 │
 └── Rejected → Stream denied
```

---

# Chapter 1: Understanding the Streaming Stack

Before installing anything, it helps to understand what each component does.

## Nginx

Nginx is the central server.

In a normal web server installation, Nginx primarily serves HTTP content. In this project, it performs several jobs:

* HTTP web server
* RTMP server
* HLS delivery
* VOD delivery
* PHP-FPM frontend
* Authentication integration
* Static file delivery

Nginx is therefore the central point through which the different parts of the platform communicate.

---

## RTMP

**RTMP**, or Real-Time Messaging Protocol, is commonly used for sending live video from broadcasting software to a streaming server.

In this setup:

```text
OBS → RTMP → Nginx
```

The RTMP connection is primarily used for **ingest**.

In other words, RTMP is how the broadcaster sends the stream to your server.

---

## HLS

**HLS**, or HTTP Live Streaming, is a streaming format designed around HTTP.

Rather than sending one continuous video connection to a viewer, the server creates:

* A playlist
* Small media segments

A simplified example looks like:

```text
stream.m3u8
segment001.ts
segment002.ts
segment003.ts
...
```

The web player periodically reads the playlist and downloads the appropriate segments.

This makes HLS particularly useful for browser-based playback.

---

## VOD

**VOD**, or Video on Demand, refers to recorded video that can be watched after a livestream has ended.

For example:

```text
Live stream
     ↓
Recording
     ↓
/var/www/vod/
     ↓
VOD player
```

Unlike HLS live segments, VOD files are intended to remain available.

---

## PHP-FPM

PHP-FPM is the component that executes PHP applications.

Nginx does not normally execute PHP code itself.

Instead:

```text
Browser
   ↓
Nginx
   ↓
PHP-FPM
   ↓
PHP script
```

In this project PHP can be used for things such as:

* Stream authentication
* Web interfaces
* Clip management
* VOD listings
* User-facing functionality

---

## FFmpeg

FFmpeg is a multimedia processing toolkit.

It can be used for:

* Converting video
* Inspecting media files
* Extracting clips
* Transcoding
* Recording
* Debugging media formats

Even when Nginx handles the actual streaming pipeline, FFmpeg is extremely useful for media administration.

---

## OBS Studio

OBS Studio is the broadcaster-side application.

It captures the content you want to broadcast and sends it to the server.

Typical workflow:

```text
Game/Desktop
     ↓
OBS
     ↓
RTMP
     ↓
Nginx
```

---

# Chapter 2: Planning the Server

## Hardware

Streaming servers do not necessarily require extremely powerful hardware.

The required resources depend heavily on what the server is doing.

For example:

| Workload                  | Resource Concern  |
| ------------------------- | ----------------- |
| RTMP ingest               | CPU/network       |
| HLS generation            | CPU/disk/network  |
| VOD storage               | Disk              |
| Video transcoding         | CPU/GPU           |
| Many simultaneous viewers | Network bandwidth |
| Many simultaneous streams | CPU/network       |
| Clip generation           | CPU/disk          |

A server that simply receives and distributes an already encoded stream can require considerably fewer resources than a server performing several simultaneous transcodes.

---

## Network Requirements

The server needs enough network bandwidth for:

* Incoming streams
* Outgoing viewers
* VOD downloads
* Clips
* Web traffic

For example, if a stream is approximately:

```text
6 Mbps
```

and ten viewers watch it simultaneously, the outgoing bandwidth requirement can approach:

```text
6 Mbps × 10 = 60 Mbps
```

before accounting for protocol overhead and other traffic.

---

## Storage

VODs and clips can consume considerably more storage than the live-stream infrastructure itself.

Plan storage according to:

* Number of streams
* Recording bitrate
* Recording duration
* Number of clips
* Retention period

Monitoring disk usage is therefore an important part of operating the server.

---

## Directory Layout

This guide uses:

```text
/var/www/
├── html/
├── hls/
├── vod/
└── clip/
```

Each directory has a distinct role.

| Directory | Purpose         |
| --------- | --------------- |
| `html`    | Website and PHP |
| `hls`     | Live HLS data   |
| `vod`     | Recorded video  |
| `clip`    | Clips           |

---

## Ports

The example installation uses:

|   Port | Purpose                    |
| -----: | -------------------------- |
| `1935` | RTMP                       |
| `9090` | Example HTTP/web interface |
|   `80` | HTTP, if configured        |
|  `443` | HTTPS, if configured       |

Your actual configuration may use different ports.

---

# Chapter 3: Preparing the Operating System

## Install Dependencies

Update the operating system:

```bash
sudo apt update
```

Install the required build dependencies:

```bash
sudo apt install -y \
    build-essential \
    git \
    libpcre2-dev \
    libssl-dev \
    zlib1g-dev \
    libxml2-dev \
    ffmpeg
```

These packages provide the compiler, libraries, source-control tools, and media utilities required to build the server.

---

## Install OBS and VLC

Install **OBS Studio** on the computer that will be broadcasting.

Install **VLC Media Player** on a computer that will be used for playback testing.

They serve different purposes:

```text
OBS → Broadcast
VLC → Test playback
```

---

# Chapter 4: Building Nginx

## Create a Source Directory

Keep source code in a dedicated location:

```bash
cd ~
mkdir -p src
cd ~/src
```

---

## Download Nginx

Download the Nginx source:

```bash
wget https://nginx.org/download/nginx-1.31.0.tar.gz
```

Extract it:

```bash
tar -zxvf nginx-1.31.0.tar.gz
```

Enter the source directory:

```bash
cd nginx-1.31.0
```

---

## Download the Streaming Modules

Clone the RTMP module:

```bash
git clone https://github.com/arut/nginx-rtmp-module.git
```

Clone the VOD module:

```bash
git clone https://github.com/kaltura/nginx-vod-module.git
```

Your source directory should now contain the Nginx source and both modules.

---

## Configure Nginx

Nginx is being compiled rather than installed from the distribution's normal package repository because the streaming modules need to be included during compilation.

### x86-64

```bash
./configure \
    --prefix=/etc/nginx \
    --conf-path=/etc/nginx/nginx.conf \
    --error-log-path=/var/log/nginx/error.log \
    --http-log-path=/var/log/nginx/access.log \
    --pid-path=/run/nginx.pid \
    --sbin-path=/usr/sbin/nginx \
    --with-http_ssl_module \
    --with-http_v2_module \
    --with-http_stub_status_module \
    --with-http_realip_module \
    --with-file-aio \
    --with-threads \
    --with-stream \
    --with-cc-opt="-O3 -mpopcnt" \
    --add-module=../nginx-vod-module-1.33 \
    --add-module=../nginx-rtmp-module
```

### ARM

```bash
./configure \
    --prefix=/etc/nginx \
    --conf-path=/etc/nginx/nginx.conf \
    --error-log-path=/var/log/nginx/error.log \
    --http-log-path=/var/log/nginx/access.log \
    --pid-path=/run/nginx.pid \
    --sbin-path=/usr/sbin/nginx \
    --with-http_ssl_module \
    --with-http_v2_module \
    --with-http_stub_status_module \
    --with-http_realip_module \
    --with-file-aio \
    --with-threads \
    --with-stream \
    --with-cc-opt="-O3" \
    --add-module=../nginx-vod-module-1.33 \
    --add-module=../nginx-rtmp-module
```

> **Important:** Verify the actual name of the VOD module directory. If your clone created `nginx-vod-module` rather than `nginx-vod-module-1.33`, change the `--add-module` path accordingly.

---

## Compile and Install

Build Nginx:

```bash
make
```

Install it:

```bash
sudo make install
```

---

## Verify the Build

Run:

```bash
nginx -V
```

Look through the output for the configured modules.

You should see references to both:

```text
nginx-rtmp-module
```

and:

```text
nginx-vod-module
```

This confirms that the custom modules were included in the build.

---

# Chapter 5: Configuring PHP-FPM

## Install PHP-FPM

Install PHP-FPM:

```bash
sudo apt install -y php-fpm
```

---

## Determine the PHP Version

Check the installed version:

```bash
php -v
```

For example:

```text
PHP 8.4.x
```

The service may therefore be named:

```text
php8.4-fpm
```

The exact version depends on your distribution.

---

## Why PHP-FPM Is Used

PHP-FPM provides the execution environment for PHP scripts.

For this project, an important example is:

```text
auth.php
```

The authentication flow becomes:

```text
OBS
 ↓
Nginx
 ↓
on_publish
 ↓
auth.php
 ↓
Allow / Reject
```

This allows authentication logic to be separated from the Nginx configuration itself.

---

# Chapter 6: Configuring Nginx

## Install the Configuration

Enter the Nginx configuration directory:

```bash
cd /etc/nginx
```

Back up an existing configuration before replacing it:

```bash
sudo cp nginx.conf nginx.conf.backup
```

Remove or replace the existing configuration as appropriate.

If downloading a configuration from GitHub, make sure you download the **raw file**, rather than the GitHub `blob` page.

For example, a raw download should point to the repository's raw content rather than:

```text
github.com/user/repository/blob/main/nginx.conf
```

---

## Review the Configuration

Open the configuration:

```bash
sudo nano /etc/nginx/nginx.conf
```

Review settings including:

* Server addresses
* Ports
* RTMP applications
* HLS paths
* VOD paths
* Clip paths
* PHP-FPM socket
* Authentication endpoint
* Logging

Do not assume that the IP addresses in an example configuration will match your own network.

---

## Test the Configuration

Always test before starting or reloading Nginx:

```bash
sudo nginx -t
```

If the test fails, fix the configuration before continuing.

---

# Chapter 7: Setting Up Web and Media Storage

## Create the Directories

Create the required directories:

```bash
sudo mkdir -p /var/www/html
sudo mkdir -p /var/www/hls
sudo mkdir -p /var/www/vod
sudo mkdir -p /var/www/clip
```

---

## Directory Permissions

Set ownership:

```bash
sudo chown -R www-data:www-data /var/www
```

Set permissions:

```bash
sudo chmod -R 755 /var/www
```

The exact permissions may need to be adjusted depending on how your particular Nginx configuration writes HLS, VOD, and clip files.

---

## Install the Web Interface

Place the project's web files inside:

```text
/var/www/html/
```

For example:

```text
/var/www/html/
├── index.php
├── auth.php
└── ...
```

The web interface can then provide:

* Live stream playback
* VOD browsing
* Clip browsing
* Authentication endpoints
* Other streaming-related functionality

---

# Chapter 8: Understanding RTMP Authentication

## Why Authentication Matters

An unprotected RTMP endpoint can potentially allow anyone who can reach the server to attempt to publish.

Authentication provides a way to control who is allowed to stream.

---

## The Authentication Process

The basic flow is:

```text
OBS
 │
 │ RTMP Publish
 ▼
Nginx
 │
 │ on_publish
 ▼
auth.php
 │
 ├── Valid credentials
 │       ↓
 │    Accept
 │
 └── Invalid credentials
         ↓
       Reject
```

The authentication request happens when a streamer attempts to begin publishing.

---

## Stream Identities

A server can support multiple streams by giving each stream an identity.

For example:

```text
runescape
wow
test
```

Each identity can have its own credentials and configuration.

---

## Protecting Stream Keys

A stream key should be treated like a password.

Do not:

* Post it publicly.
* Put it in screenshots.
* Include it in public documentation.
* Put it into the viewer URL.
* Commit it to a public Git repository.

If a key is compromised, replace it.

---

# Chapter 9: Configuring OBS Studio

## Create an RTMP Connection

Open:

```text
OBS Studio
→ Settings
→ Stream
```

Set the service to:

```text
Custom...
```

For the example server:

```text
rtmp://10.0.0.65:1935/live
```

Replace the example address with the address of your own server.

---

## Configure the Stream Key

The exact stream-key format depends on your authentication implementation.

For example:

```text
runescape?name=runescape&key=<YOUR_SECRET>
```

The important distinction is:

```text
runescape
```

is the stream identity, while:

```text
<YOUR_SECRET>
```

is the private credential.

---

## Create an OBS Scene

Create a scene and add the appropriate sources.

For a RuneScape stream, for example:

```text
Sources
→ +
→ Window Capture
```

Select the RuneScape or RuneLite window.

You can also add:

* Microphone
* Webcam
* Images
* Browser sources
* Overlays
* Alerts
* Desktop audio

Verify the audio meters before streaming.

---

## Starting a Stream

Click:

```text
Start Streaming
```

OBS then begins the publishing process.

The server should:

1. Receive the RTMP connection.
2. Extract the publishing information.
3. Call the authentication endpoint.
4. Validate the credentials.
5. Accept or reject the stream.
6. Begin processing the stream if authentication succeeds.

---

# Chapter 10: Live Streaming and HLS

## The Live Streaming Pipeline

The live workflow is:

```text
Game / Desktop
      │
      ▼
     OBS
      │
      │ RTMP
      ▼
    Nginx
      │
      │ HLS
      ▼
 /var/www/hls
      │
      ▼
 Web Player
```

---

## HLS Output

When HLS is enabled, Nginx generates live-streaming data in the configured HLS directory.

For example:

```text
/var/www/hls/
```

You may see playlist and segment files appear while a stream is active.

These files represent the current live stream rather than a permanent VOD archive.

---

## Watching the Stream

A player can be given the public stream identity.

For example:

```text
http://10.0.0.65:9090/index.php?streamkey=runescape
```

The viewer URL should contain the stream identity but **not the private publishing credential**.

---

# Chapter 11: Video on Demand

## What Is VOD?

VOD allows viewers to watch previously recorded content.

Instead of:

```text
Live → Viewer
```

VOD provides:

```text
Recording → Storage → Viewer
```

---

## VOD Storage

The example storage location is:

```text
/var/www/vod/
```

For example:

```text
/var/www/vod/
├── stream-2026-09-20.mp4
├── stream-2026-09-21.mp4
└── stream-2026-09-22.mp4
```

---

## VOD Playback

The Nginx VOD module allows the server to expose compatible video files through Nginx.

The web application can then provide a catalog or player for those files.

VOD storage should be monitored because recorded streams can consume large amounts of disk space.

---

# Chapter 12: Clips

## Clip Storage

Clips are stored separately:

```text
/var/www/clip/
```

This allows the server to distinguish short highlights from complete recordings.

---

## Clip Workflow

A typical workflow might look like:

```text
Livestream
    ↓
Recording
    ↓
FFmpeg / Clip Tool
    ↓
Short Clip
    ↓
/var/www/clip/
    ↓
Clip Player
```

FFmpeg can be used to extract sections of a larger recording.

---

# Chapter 13: Operating the Server

## Starting Nginx

Enable Nginx at boot:

```bash
sudo systemctl enable nginx
```

Start it:

```bash
sudo systemctl start nginx
```

---

## Reloading Configuration

After modifying `nginx.conf`:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Always test the configuration first.

---

## Checking Service Status

```bash
systemctl status nginx
```

For PHP-FPM:

```bash
systemctl status php*-fpm
```

---

## Monitoring Logs

Nginx error log:

```bash
sudo tail -f /var/log/nginx/error.log
```

Nginx access log:

```bash
sudo tail -f /var/log/nginx/access.log
```

Logs are especially useful when debugging authentication, HTTP, RTMP, and HLS problems.

---

# Chapter 14: Testing and Troubleshooting

## Testing Nginx

Run:

```bash
sudo nginx -t
```

This should be your first step after making configuration changes.

---

## Testing the Web Server

Open the web interface in a browser:

```text
http://SERVER-IP/
```

If the web interface does not load, investigate the HTTP configuration before troubleshooting streaming.

---

## Testing RTMP

Start a test stream in OBS.

Then watch:

```bash
sudo tail -f /var/log/nginx/error.log
```

Look for:

* Authentication failures
* RTMP connection errors
* Incorrect application names
* Permission problems

---

## Testing HLS

While streaming, inspect:

```bash
ls -lah /var/www/hls
```

HLS files should appear if the configuration is working correctly.

---

## Testing PHP

Check PHP-FPM:

```bash
systemctl status php*-fpm
```

If PHP pages return errors or download instead of executing, check the Nginx PHP configuration and PHP-FPM socket.

---

## Testing VOD

Verify that:

```text
/var/www/vod/
```

contains the expected media files.

Then test playback through the configured VOD player.

---

## Common Problems

### Nginx will not start

Run:

```bash
sudo nginx -t
```

Then:

```bash
sudo journalctl -u nginx
```

---

### OBS cannot connect

Check:

* Server IP
* RTMP port
* RTMP application
* Firewall
* Stream key
* Authentication
* Nginx status

---

### OBS connects and immediately disconnects

Check:

```bash
sudo tail -f /var/log/nginx/error.log
```

This commonly points toward authentication or RTMP configuration problems.

---

### Authentication fails

Verify:

* Stream identity
* Secret token
* Parameter names
* Authentication URL
* PHP-FPM
* `auth.php`

---

### HLS is not generated

Check:

```bash
ls -lah /var/www/hls
```

and:

```bash
sudo tail -f /var/log/nginx/error.log
```

Also verify that the RTMP application has HLS configured.

---

### PHP does not execute

Check the PHP-FPM service:

```bash
systemctl status php*-fpm
```

Then verify that the configured socket matches the installed PHP version.

---

### VOD files do not play

Verify:

* The file exists.
* Nginx can read it.
* The VOD module is installed.
* The VOD location is configured correctly.
* The media format is compatible.

Check the Nginx build:

```bash
nginx -V
```

---

# Chapter 15: Security and Maintenance

## Stream Authentication

Never leave the publishing endpoint open unless anonymous publishing is specifically intended.

Use `on_publish` authentication to restrict publishing access.

---

## HTTPS

If the web interface is exposed to the Internet, HTTPS should be considered for the HTTP side of the service.

HTTPS protects web traffic between viewers and the server.

It is separate from the RTMP ingest connection and should therefore be considered independently when designing the system.

---

## Firewall Configuration

Only expose ports that are actually required.

For example:

```text
1935 → RTMP
80   → HTTP
443  → HTTPS
```

If a web interface is intentionally hosted on another port, expose that port instead.

---

## File Permissions

Avoid making media directories globally writable.

The Nginx worker process should have only the permissions necessary to perform its job.

Review:

```bash
ls -lah /var/www
```

and verify ownership and permissions.

---

## Storage Management

Monitor disk space:

```bash
df -h
```

Also monitor the size of:

```text
/var/www/vod/
/var/www/clip/
/var/www/hls/
```

VOD and clip storage can grow indefinitely if old content is never removed.

---

## Logging and Monitoring

Logs can reveal:

* Failed authentication
* Unexpected clients
* HTTP errors
* RTMP errors
* PHP problems
* Permission problems

For a larger deployment, consider adding dedicated monitoring and alerting.

---

# Chapter 16: Expanding the Platform

The basic server can serve as the foundation for a much larger system.

## Multiple Streamers

Multiple stream identities can be configured:

```text
runescape
wow
minecraft
test
```

Each streamer can receive a separate stream key.

---

## Automatic Recording

A live stream can be recorded automatically and moved into the VOD library when the broadcast ends.

A possible workflow is:

```text
OBS
 ↓
RTMP
 ↓
Nginx
 ↓
Recording
 ↓
/var/www/vod/
```

---

## Automated Clips

FFmpeg or another application can be used to generate clips automatically.

For example:

```text
VOD
 ↓
Start timestamp
 ↓
End timestamp
 ↓
FFmpeg
 ↓
Clip
```

---

## User Accounts

The PHP web application can eventually be expanded to support:

* User accounts
* Streamer profiles
* Stream management
* VOD management
* Clip management
* Authentication
* Administrative controls

---

## Monitoring

A larger server can also incorporate monitoring for:

* CPU usage
* RAM usage
* Disk usage
* Network traffic
* Active streams
* Viewer counts
* Nginx status
* PHP-FPM status

---

# Appendix A: Useful Commands

## Nginx Version

```bash
nginx -V
```

## Test Configuration

```bash
sudo nginx -t
```

## Start Nginx

```bash
sudo systemctl start nginx
```

## Stop Nginx

```bash
sudo systemctl stop nginx
```

## Restart Nginx

```bash
sudo systemctl restart nginx
```

## Reload Nginx

```bash
sudo systemctl reload nginx
```

## Enable Nginx at Boot

```bash
sudo systemctl enable nginx
```

## Check Nginx Status

```bash
systemctl status nginx
```

## View Error Log

```bash
sudo tail -f /var/log/nginx/error.log
```

## View Access Log

```bash
sudo tail -f /var/log/nginx/access.log
```

## Check Disk Space

```bash
df -h
```

## Check HLS Files

```bash
ls -lah /var/www/hls
```

## Check VOD Files

```bash
ls -lah /var/www/vod
```

## Check Clips

```bash
ls -lah /var/www/clip
```

## Check PHP

```bash
php -v
```

## Check PHP-FPM

```bash
systemctl status php*-fpm
```

---

# Appendix B: Example Directory Structure

A complete installation might look like:

```text
/etc/nginx/
└── nginx.conf

/var/www/
├── html/
│   ├── index.php
│   ├── auth.php
│   └── ...
│
├── hls/
│   └── live HLS segments
│
├── vod/
│   └── recorded videos
│
└── clip/
    └── video clips

~/src/
├── nginx-1.31.0/
├── nginx-rtmp-module/
└── nginx-vod-module/
```

This separates:

```text
Configuration
    ↓
/etc/nginx/

Web application
    ↓
/var/www/html/

Live media
    ↓
/var/www/hls/

Permanent recordings
    ↓
/var/www/vod/

Short-form recordings
    ↓
/var/www/clip/

Source code
    ↓
~/src/
```

---

# Appendix C: Glossary

## Authentication

The process of verifying that a user, application, or stream has permission to access a service.

In this guide, authentication is primarily used to determine whether a broadcaster is allowed to publish an RTMP stream.

---

## Bitrate

The amount of data used to represent a video or audio stream, normally measured in bits per second.

Common examples include:

```text
2 Mbps
6 Mbps
10 Mbps
```

Higher bitrates generally allow more data to be used for the media, but require more bandwidth and storage.

---

## Clip

A short section of a longer video recording.

For example:

```text
2-hour VOD
    ↓
30-second highlight
```

---

## Encoder

Software or hardware that converts raw audio/video into a compressed format suitable for storage or transmission.

OBS performs encoding before sending a stream to the server.

---

## FFmpeg

A collection of multimedia tools used for processing audio and video.

It can perform tasks such as:

* Encoding
* Decoding
* Transcoding
* Cutting
* Joining
* Extracting
* Inspecting media

---

## HLS

**HTTP Live Streaming.**

A streaming protocol that delivers video using HTTP playlists and media segments.

The basic structure is:

```text
.m3u8 playlist
     ↓
media segments
```

---

## HLS Segment

A small piece of an HLS stream.

Instead of one giant live video file, the stream is divided into multiple segments.

---

## Ingest

The process of receiving a livestream from the broadcaster.

In this setup:

```text
OBS → Nginx
```

is the ingest path.

---

## Nginx

A high-performance web server that can also be extended with additional modules.

In this project Nginx acts as:

* Web server
* RTMP server
* HLS server
* VOD server
* Reverse/proxy layer where applicable
* PHP-FPM frontend

---

## nginx-rtmp-module

An Nginx module that adds RTMP functionality.

It allows Nginx to receive and process RTMP publishing connections.

---

## nginx-vod-module

The Kaltura Nginx VOD module adds video-on-demand functionality to Nginx.

---

## OBS Studio

Open Broadcaster Software.

An application used to capture and encode video/audio and publish it to a streaming server.

---

## PHP-FPM

**PHP FastCGI Process Manager.**

A service that executes PHP applications for a web server such as Nginx.

---

## RTMP

**Real-Time Messaging Protocol.**

A protocol commonly used by broadcasting applications to send live video to streaming servers.

In this project:

```text
OBS → RTMP → Nginx
```

---

## RTMP Application

A logical RTMP endpoint configured in Nginx.

For example:

```text
rtmp://server.example/live
```

Here:

```text
live
```

is the application name.

---

## Stream Key

A credential or identifier used by a streaming platform to identify and/or authenticate a broadcaster.

A stream key should generally be treated as a secret.

---

## Stream Identity

The public identifier associated with a stream.

For example:

```text
runescape
```

The identity can be used by the web player to select which stream should be displayed.

---

## Transcoding

Converting media from one encoding, resolution, bitrate, or format into another.

For example:

```text
1080p 60 FPS
      ↓
720p 30 FPS
```

Transcoding can require substantial CPU or GPU resources.

---

## VOD

**Video on Demand.**

Recorded video that can be watched after the original livestream has ended.

---

## Viewer

A client that consumes a stream.

For example:

```text
Browser
VLC
Mobile application
```

---

## Web Player

The application or webpage responsible for displaying a livestream or video to viewers.

The web player may use HLS, VOD, or another playback technology depending on the configuration.

---

## PHP Authentication Endpoint

A PHP script that receives an authentication request and determines whether a stream should be allowed.

In this guide, the example is:

```text
auth.php
```

It can be called by Nginx through `on_publish`.

---

## on_publish

An Nginx RTMP directive that can trigger an HTTP request when a client attempts to publish a stream.

It can be used to connect Nginx to an external authentication system.

Conceptually:

```text
OBS
 ↓
Nginx
 ↓
on_publish
 ↓
Authentication service
 ↓
Allow / Reject
```

---

## RTMP Ingest

The process of accepting an RTMP stream from a broadcaster.

```text
Broadcaster
    ↓
RTMP
    ↓
Streaming Server
```

---

## Live Stream

A stream that is being generated and delivered while the broadcaster is actively producing content.

Unlike VOD, the content does not necessarily exist as a completed file before viewers begin watching.

---

## VOD Storage

Permanent or semi-permanent storage containing recorded video.

In this guide:

```text
/var/www/vod/
```

is used as the example VOD storage directory.

---

## HLS Playlist

An `.m3u8` file describing the media segments that make up an HLS stream.

It tells the player which segments should be requested.

---

## PHP

A server-side programming language commonly used to create dynamic websites and web applications.

In this project it can be used for the streaming website and authentication logic.

---

## RTMP Publisher

The software or client that sends a stream to the RTMP server.

In this setup, OBS is the publisher.

---

## Streaming Server

A server responsible for receiving, processing, storing, and/or distributing live or recorded media.

This guide builds one using Nginx and several supporting components.

---

# Final Architecture

After completing the guide, the overall system can be thought of as four major layers:

```text
┌─────────────────────────────────────────────┐
│                 BROADCASTER                 │
│                                             │
│                  OBS Studio                 │
└──────────────────────┬──────────────────────┘
                       │
                       │ RTMP
                       ▼
┌─────────────────────────────────────────────┐
│                STREAM SERVER                │
│                                             │
│                    Nginx                    │
│                                             │
│       ┌──────────────┬──────────────┐       │
│       │              │              │       │
│       ▼              ▼              ▼       │
│     RTMP            HLS            VOD      │
│                                             │
│                 Authentication              │
│                     PHP                    │
└──────────────────────┬──────────────────────┘
                       │
                       │ HTTP / HLS / VOD
                       ▼
┌─────────────────────────────────────────────┐
│                    USERS                    │
│                                             │
│     Web Browser       VLC       Other Apps  │
└─────────────────────────────────────────────┘
```

The server can therefore serve as more than an RTMP endpoint. With the appropriate configuration and supporting applications, it becomes a complete self-hosted media platform capable of **live streaming, authentication, HLS playback, VOD hosting, clip hosting, and web-based media management**.
