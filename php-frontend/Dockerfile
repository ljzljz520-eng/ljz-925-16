FROM node:20-bookworm-slim AS frontend-protector

WORKDIR /workspace

COPY scripts/ /workspace/scripts/
COPY php-frontend/ /workspace/php-frontend/

RUN node scripts/protect-frontend.mjs

FROM php:8.3-apache

# 启用Apache mod_rewrite模块
RUN a2enmod rewrite

# 安装必要的PHP扩展
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY --from=frontend-protector /workspace/php-frontend/public/ /var/www/html/
COPY --from=frontend-protector /workspace/php-frontend/includes/ /var/www/includes/
COPY --from=frontend-protector /workspace/php-frontend/assets/ /var/www/html/assets/

# 设置Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html

# 更新Apache配置
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 配置Apache允许.htaccess
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
    && a2enconf override

# 设置文件权限
RUN chown -R www-data:www-data /var/www/html /var/www/includes \
    && chmod -R 755 /var/www/html /var/www/includes

# 暴露端口
EXPOSE 80

# 启动Apache
CMD ["apache2-foreground"]
