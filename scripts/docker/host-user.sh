#!/usr/bin/env bash
# Prints the user:group a container has to run as, or chown to, for the files
# it writes into a mounted checkout to belong to the person who ran it.
#
#   docker run --user "$(scripts/docker/host-user.sh)" ...
#
# With Docker running as root that is the invoking user's own uid:gid.
# Rootless Docker maps the container's root to the invoking user instead, and
# the invoking user's uid to an unprivileged one of its own, so there it is
# root.
set -euo pipefail

readonly CONTAINER_ROOT='0:0'

if docker info --format '{{.SecurityOptions}}' | grep -q 'name=rootless'; then
    echo "$CONTAINER_ROOT"
else
    echo "$(id -u):$(id -g)"
fi
