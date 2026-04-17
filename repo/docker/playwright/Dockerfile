# Build-time install of Playwright + frontend deps + Chromium browser.
# Removes the need to run `npm install` and `playwright install` at test runtime.
FROM mcr.microsoft.com/playwright:v1.44.0-jammy

WORKDIR /var/www/frontend

# Install frontend deps (includes @playwright/test) from the lockfile during build.
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

# Pre-install the Chromium browser binary inside the image.
RUN npx playwright install chromium

# Source is bind-mounted at runtime; node_modules survives via the anonymous
# volume declared in docker-compose.yml.
ENV HOME=/root

CMD ["npx", "playwright", "test", "--config=playwright.config.ts", "--reporter=list"]
