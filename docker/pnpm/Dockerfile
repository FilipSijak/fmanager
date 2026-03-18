FROM node:22-bullseye AS base

ENV PNPM_HOME="/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
ENV COREPACK_ENABLE_AUTO_PIN=0
RUN corepack enable
RUN corepack prepare pnpm@10.30.1 --activate
WORKDIR /workspace

COPY docker/pnpm/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

FROM base AS dev
COPY --from=docker:dind /usr/local/bin/docker /usr/local/bin/
