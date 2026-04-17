# Build-time install of frontend dependencies for the Vite dev server.
# Eliminates the need for `npm install` at container start / test runtime.
FROM node:18-alpine

WORKDIR /var/www/frontend

# Install deps from lockfile during image build (deterministic, network-isolated at runtime).
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

# Source is bind-mounted at runtime; node_modules is preserved via an
# anonymous volume declared in docker-compose.yml so the host bind mount
# does not shadow the dependencies installed here.
EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
