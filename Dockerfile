# Dockerfile for Static HTML Website - Optimized for Render.com
FROM nginx:alpine

# ============================================
# Environment Variables
# ============================================
ENV NGINX_DOCUMENT_ROOT /usr/share/nginx/html

# ============================================
# Install curl for health checks
# ============================================
RUN apk add --no-cache curl

# ============================================
# Copy HTML Files
# ============================================
# Copy all HTML files and assets
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
# Custom Nginx Configuration (Optional)
# ============================================
# Create custom nginx config for better performance
RUN echo 'server {\n\
    listen 8080;\n\
    server_name localhost;\n\
    root /usr/share/nginx/html;\n\
    index index.html index.htm;\n\
    \n\
    # Gzip compression\n\
    gzip on;\n\
    gzip_types text/html text/css text/javascript application/javascript application/json;\n\
    \n\
    # Cache static assets\n\
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|webp)$ {\n\
        expires 1y;\n\
        add_header Cache-Control "public, immutable";\n\
    }\n\
    \n\
    # Security headers\n\
    add_header X-Frame-Options "SAMEORIGIN" always;\n\
    add_header X-Content-Type-Options "nosniff" always;\n\
    add_header X-XSS-Protection "1; mode=block" always;\n\
    \n\
    # Handle 404\n\
    error_page 404 /404.html;\n\
    location = /404.html {\n\
        internal;\n\
    }\n\
}' > /etc/nginx/conf.d/default.conf

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
