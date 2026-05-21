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
