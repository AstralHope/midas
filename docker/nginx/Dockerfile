# Dockerfile to compile and install Nginx on Debian

# ===================================================================
# STAGE 1: Build Nginx from source
# ===================================================================
# 使用官方的 Debian 12 (Bookworm) slim 版本作为基础镜像
FROM debian:bookworm-slim AS builder

# 设置 NGINX 版本，方便后续更新
ENV NGINX_VERSION 1.30.0
# 设置编译参数，可以根据需要自行增减模块
# --with-http_ssl_module 是为了支持 HTTPS
# --with-http_v2_module 是为了支持 HTTP/2
# --with-http_stub_status_module 提供状态监控页面
# --with-threads 使用线程池
# --with-stream 支持四层代理
# --with-stream_ssl_module 为四层代理提供 SSL 支持
ENV CONFIG_ARGS "\
    --user=nginx \
    --group=nginx \
    --prefix=/usr/nginx/ \
    --with-pcre \
    --with-http_ssl_module \
    --with-http_stub_status_module \
    --with-http_gzip_static_module \
    --http-client-body-temp-path=/usr/nginx/tmp/client \
    --http-proxy-temp-path=/usr/nginx/tmp/proxy \
    --with-http_realip_module \
    --with-stream \
    --with-http_sub_module \
    --add-module=../nginx-module-vts-0.2.5 \
    "

# 安装编译所需的依赖包
# --no-install-recommends 可以减少镜像体积
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    build-essential \
    ca-certificates \
    wget \
    libpcre3-dev \
    libssl-dev \
    zlib1g-dev \
    && echo "Dependencies installed."

# 下载、解压、编译和安装 Nginx
RUN cd /tmp && \
    mkdir -p /usr/nginx/logs && \
    mkdir -p /usr/nginx/tmp && \
    mkdir -p /usr/nginx/conf/http.d && \
    mkdir -p /usr/nginx/conf/stream.d 
RUN wget "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" && \
    tar -zxvf nginx-${NGINX_VERSION}.tar.gz && \
    wget "https://github.com/vozlt/nginx-module-vts/archive/refs/tags/v0.2.5.tar.gz" && \
    tar -zxvf v0.2.5.tar.gz && \
    cd nginx-${NGINX_VERSION} && \
    ./configure ${CONFIG_ARGS} && \
    make -j$(nproc) && \
    make install && \
    echo "Nginx installed."

COPY nginx.conf /usr/nginx/conf/nginx.conf
COPY zabbix.conf /usr/nginx/conf/http.d/zabbix.conf

# ===================================================================
# STAGE 2: Create the final, smaller image
# ===================================================================
# 同样使用 Debian 12 slim 作为最终运行环境的基础镜像
FROM debian:bookworm-slim

# 设置标签，描述镜像信息
#LABEL maintainer="Your Name <your.email@example.com>"
#LABEL description="Nginx compiled from source on Debian."

# 安装 Nginx 运行时的最小依赖
# ca-certificates: 用于 SSL/TLS
# libpcre3, libssl3, zlib1g: Nginx 编译时链接的动态库
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    libpcre3 \
    libssl3 \
    zlib1g \
    && apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# 添加一个非 root 用户来运行 Nginx，更安全
RUN addgroup --system --gid 10301 nginx && \
    adduser --system --disabled-login --ingroup nginx --no-create-home --home /nonexistent --gecos "nginx user" --shell /bin/false --uid 10301 nginx

# 从 builder 阶段复制编译好的 Nginx 文件到最终镜像
COPY --from=builder /usr/nginx /usr/nginx

# 创建 Nginx 需要的目录并设置权限
RUN chown -R nginx:nginx /usr/nginx

# 默认的网站根目录
#RUN mkdir -p /var/www/html && \
#    chown -R nginx:nginx /var/www/html && \
#    echo "<h1>Nginx compiled from source is running!</h1>" > /var/www/html/index.html

# 将 Nginx 日志重定向到标准输出/错误，方便 `docker logs` 查看
RUN ln -sf /dev/stdout /usr/nginx/logs/access.log && \
    ln -sf /dev/stderr /usr/nginx/logs/error.log

# 暴露端口
EXPOSE 18088

# 定义容器停止时发送给 Nginx 的信号，用于优雅退出
STOPSIGNAL SIGQUIT

# 容器启动命令
# -g 'daemon off;' 是关键，它让 Nginx 在前台运行
CMD ["/usr/nginx/sbin/nginx", "-g", "daemon off;"]
