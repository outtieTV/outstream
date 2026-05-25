# outstream
Outstream is an attempt at an nginx web server with rtmp and vod modules. Now with vods and clips!<br />
<br />
<img width="1920" height="1080" alt="image" src="https://github.com/user-attachments/assets/311ed9af-44a0-43c9-a439-e19cd62dd4c7" />
<img width="1920" height="1080" alt="image-1" src="https://github.com/user-attachments/assets/e3335145-52ba-48ea-b63e-447209c940e2" />
<img width="1920" height="1080" alt="067D4EBA-9172-4944-89B6-FE351FC94385" src="https://github.com/user-attachments/assets/8d092faa-3902-41fa-8e4e-d85a32701ac6" />

install vlc media player<br />
install obs studio<br />
<br />
terminal:<br />
$ sudo apt update && sudo apt install -y build-essential git libpcre2-dev libssl-dev zlib1g-dev libxml2-dev ffmpeg<br />
$ cd ~<br />
$ mkdir src<br />
$ cd src<br />
$ wget https://nginx.org/download/nginx-1.31.0.tar.gz<br />
$ tar -zxvf nginx-1.31.0.tar.gz<br />
$ git clone https://github.com/arut/nginx-rtmp-module.git<br />
$ git clone https://github.com/kaltura/nginx-vod-module.git<br />
$ cd nginx-1.31.0<br />
<br />
\# x86-64<br />
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
    --add-module=../nginx-rtmp-module<br />
\# arm<br />
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

$ make && sudo make install<br />
$ nginx -V<br />
$ sudo apt install php-fpm -y<br />
#note down the version it installs ex php8.4-fpm or php8.5-fpm<br />
<br />
$ cd /etc/nginx<br />
$ sudo rm nginx.conf<br />
$ wget https://github.com/outtieTV/outstream/blob/main/nginx.conf<br />
$ sudo nano nginx.conf<br />
\#edit the ip addresses and such<br />
\#ctrl o ctrl x<br />
<br />
$ cd /var/www<br />
$ sudo mkdir html vod hls clip<br />
$ sudo chown -R www-data:www-data /var/www<br />
$ sudo chmod -R 755 /var/www<br />
\#Download the html directory in this github repository to /var/www/html so that index.php is at /var/www/html/index.php.<br />
$ sudo nginx -t<br />
$ sudo systemctl enable nginx<br />
$ sudo systemctl start nginx<br />
<br />
By default, an open Nginx RTMP configuration allows anyone who knows your server IP to stream to it. To protect your server from unauthorized use, your configuration leverages an **`on_publish`** authentication directive. When you click "Start Streaming" in OBS, Nginx sends a hidden validation request behind the scenes to a PHP script to verify that you are allowed to broadcast.
---

### Step 1: Find Your Server Connection Info

* **Streaming Server Base IP:** `10.0.0.65`
* **HLS Web Panel Port:** `9090`
* **RTMP Live Ingestion Port:** `1935`

### Step 2: Determine Your Stream Key and Password

Your server looks for the stream credentials directly inside the **Stream Key** field in OBS. It splits them using a **secret password string** (or hash) appended to your stream identity using a delimiter (such as a `?` or a `_`).

For a **RuneScape** stream, your configuration checks for your specific authorized identity:

* **Stream Name / Identity:** `runescape`
* **Your Stream Key / Secret Token:** *(This is the custom password or alphanumeric hash you defined in your `config.php` or auth database, e.g., `mySecretPassword123`)*

---

### Step 3: Configure OBS Studio

1. Open **OBS Studio**.
2. Go to **Settings** (bottom right corner) $\rightarrow$ Click on the **Stream** tab in the left sidebar.
3. Change the **Service** dropdown menu to **Custom...**
4. Set your **Server** URL to point to your Nginx live application block:
```text
rtmp://10.0.0.65:1935/live

```


5. Enter your **Stream Key** formatted with your authentication token. Depending on how your `on_publish` auth script parses arguments, combine them like this:
```text
runescape?name=runescape&key=mySecretPassword123

```


*(If your backend script parses raw RTMP arguments via the name parameter, ensure your unique token matches your server's backend configuration variable exactly).*
6. Click **Apply** and then **OK**.

---

### Step 4: Set up Your RuneScape Scene in OBS

1. Locate the **Sources** panel at the bottom of the main OBS window.
2. Click the **`+`** icon and select **Window Capture** (ideal for the RuneScape Launcher or RuneLite client).
3. Name it `RuneScape Client` and click OK.
4. Select your RuneScape/RuneLite client from the **Window** dropdown menu.
5. In the **Audio Mixer** panel, verify your desktop audio device is unmuted so game sounds are captured.

---

### Step 5: Go Live & Handshake Verification

1. Click **Start Streaming** in OBS.
2. **What happens under the hood:** Nginx catches the connection, extracts your stream key details, and immediately fires a local request to your authentication script (`/var/www/html/auth.php` or your designated authentication endpoint).
3. If the token matches, the script returns a `200 OK` HTTP status code, and OBS will display a steady green connection block. If the token is missing or wrong, the script returns a `404` or `403`, and OBS will instantly disconnect with an "Unauthorized / Connection failed" error.

---

### Step 6: Watch the Stream

Once the server successfully verifies your stream handshake, navigate to your player interface to view the live HLS feed. You only need to pass the stream identity to the web player, **not** your private streaming password:

```text
http://10.0.0.65:9090/index.php?streamkey=runescape

```

