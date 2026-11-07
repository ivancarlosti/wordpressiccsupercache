vcl 4.1;

# End-to-end stack VCL (see docker/docker-compose.yml).
# The purge key must match ICC_GG_SUPER_CACHE_VARNISH_KEY in the WordPress
# container and ICC_GG_VARNISH_KEY in docker/.env.

backend default {
    .host = "nginx";
    .port = "80";
}

sub vcl_recv {
    if (req.method == "BAN") {
        if (req.http.X-Purge-Key != "dev-purge-key") {
            return (synth(403, "Forbidden"));
        }

        ban req.http.host == req.http.X-Purge-Host && req.url ~ req.http.X-Purge-Path;

        return (synth(200, "Purged"));
    }

    if (req.method == "PURGE") {
        if (req.http.X-Purge-Key != "dev-purge-key") {
            return (synth(403, "Forbidden"));
        }

        return (purge);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    if (req.http.Authorization || req.http.Cookie ~ "wordpress_logged_in|comment_author|wp-postpass|wordpress_sec|wp_woocommerce_session") {
        return (pass);
    }

    return (hash);
}

sub vcl_backend_response {
    if (beresp.http.Set-Cookie) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;

        return (deliver);
    }

    if (beresp.status != 200 && beresp.status != 301 && beresp.status != 302) {
        set beresp.ttl = 0s;
    } else {
        set beresp.ttl = 1h;
        set beresp.grace = 10m;
    }

    return (deliver);
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Varnish-Cache = "HIT";
    } else {
        set resp.http.X-Varnish-Cache = "MISS";
    }

    set resp.http.X-Varnish-Hits = obj.hits;

    return (deliver);
}
