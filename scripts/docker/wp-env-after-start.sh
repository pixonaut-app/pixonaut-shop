#!/usr/bin/env bash
# Runs after every `wp-env start` (`lifecycleScripts` in .wp-env.json).
#
# wp-env runs WordPress as the invoking user's uid:gid, which under rootless
# Docker is not the invoking user on the host (see host-user.sh). WordPress
# then cannot write to the wp-content directories wp-env created on the host,
# so uploads and language packs fail. Nor can it write the
# WordPress root, so the wp-cli.yml and .htaccess wp-env writes there during
# its first start are silently left out, and every pretty permalink and the
# REST API answer 404. Opening those directories to everyone is enough for a
# local environment; the two files are then written again. Where the
# container's user is the invoking user, this does nothing.
set -euo pipefail

# The directories under wp-content that WordPress creates files in.
readonly WRITABLE_DIRS=(uploads languages upgrade)

if [[ "$("$(dirname "$0")/host-user.sh")" == "$(id -u):$(id -g)" ]]; then
    exit 0
fi

install_path=$(wp-env status --json |
    node -p 'JSON.parse(require("fs").readFileSync(0, "utf8")).installPath')

# The wp-cli.yml wp-env writes, without which WP-CLI won't write an .htaccess.
readonly WP_CLI_CONFIG=$'apache_modules:\n  - mod_rewrite'

# Each wp-env environment, by its WordPress directory and its CLI container.
declare -A CLI_CONTAINERS=([WordPress]=cli [tests-WordPress]=tests-cli)

for wordpress in "${!CLI_CONTAINERS[@]}"; do
    wp_root=$install_path/$wordpress
    [[ -d $wp_root ]] || continue
    wp_content=$wp_root/wp-content

    for dir in "$wp_root" "$wp_content" "${WRITABLE_DIRS[@]/#/$wp_content/}"; do
        mkdir -p "$dir"
        # One WordPress created itself is already its own to write to.
        if [[ -O $dir ]]; then
            chmod a+rwx "$dir"
        fi
    done

    [[ -f $wp_root/wp-cli.yml ]] || echo "$WP_CLI_CONFIG" >"$wp_root/wp-cli.yml"
    wp-env run "${CLI_CONTAINERS[$wordpress]}" wp rewrite flush --hard
done
