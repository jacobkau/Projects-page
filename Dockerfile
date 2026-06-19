# Dockerfile for Static HTML Website - Optimized for Render.com
FROM nginx:alpine

# ============================================
# Install curl for health checks
# ============================================
RUN apk add --no-cache curl

# ============================================
# Copy HTML Files
# ============================================
COPY . /usr/share/nginx/html/

# ============================================
# Remove unnecessary files (optional)
# ============================================
RUN rm -f /usr/share/nginx/html/Dockerfile \
    && rm -f /usr/share/nginx/html/.dockerignore \
    && rm -f /usr/share/nginx/html/README.md 2>/dev/null || true

# ============================================
# Set Permissions
# ============================================
RUN chown -R nginx:nginx /usr/share/nginx/html \
    && chmod -R 755 /usr/share/nginx/html

# ============================================
# Custom Nginx Configuration - CORRECTED
# ============================================
# Create custom nginx config for better performance
RUN echo 'server {' > /etc/nginx/conf.d/default.conf && \
    echo '    listen 8080;' >> /etc/nginx/conf.d/default.conf && \
    echo '    server_name localhost;' >> /etc/nginx/conf.d/default.conf && \
    echo '    root /usr/share/nginx/html;' >> /etc/nginx/conf.d/default.conf && \
    echo '    index index.html index.htm;' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    # Gzip compression' >> /etc/nginx/conf.d/default.conf && \
    echo '    gzip on;' >> /etc/nginx/conf.d/default.conf && \
    echo '    gzip_types text/html text/css text/javascript application/javascript application/json;' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    # Cache static assets' >> /etc/nginx/conf.d/default.conf && \
    echo '    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|webp)$ {' >> /etc/nginx/conf.d/default.conf && \
    echo '        expires 1y;' >> /etc/nginx/conf.d/default.conf && \
    echo '        add_header Cache-Control "public, immutable";' >> /etc/nginx/conf.d/default.conf && \
    echo '    }' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    # Security headers' >> /etc/nginx/conf.d/default.conf && \
    echo '    add_header X-Frame-Options "SAMEORIGIN" always;' >> /etc/nginx/conf.d/default.conf && \
    echo '    add_header X-Content-Type-Options "nosniff" always;' >> /etc/nginx/conf.d/default.conf && \
    echo '    add_header X-XSS-Protection "1; mode=block" always;' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    # Handle 404' >> /etc/nginx/conf.d/default.conf && \
    echo '    error_page 404 /404.html;' >> /etc/nginx/conf.d/default.conf && \
    echo '    location = /404.html {' >> /etc/nginx/conf.d/default.conf && \
    echo '        internal;' >> /etc/nginx/conf.d/default.conf && \
    echo '    }' >> /etc/nginx/conf.d/default.conf && \
    echo '}' >> /etc/nginx/conf.d/default.conf

# ============================================
# Alternative: Copy existing nginx config if you have one
# ============================================
# COPY nginx.conf /etc/nginx/conf.d/default.conf

# ============================================
# Health Check
# ============================================
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# ============================================
# Expose Port and Start Nginx
# ============================================
EXPOSE 8080
EXPOSE 80

# Start Nginx in foreground
CMD ["nginx", "-g", "daemon off;"]
