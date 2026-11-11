# Studio Jurassic Tube

A single-file mu-plugin that lets a WordPress Studio site answer on its Jurassic Tube domain.

## The problem

Studio defines `WP_HOME` and `WP_SITEURL` as `http://localhost:<port>` before WordPress boots. A request for `<sub>.jurassic.tube` is therefore redirected to `https://<sub>.jurassic.tube:<port>/`, which nothing serves.

## What the plugin does

When a request arrives on a host ending in `.jurassic.tube`, the plugin:

- marks the request as HTTPS;
- reports `https://<that host>` as the home and site URL, which stops the redirect;
- rewrites the local origin in plugin, theme and upload URLs to the tunnel host.

Requests on `localhost:<port>` or a Studio `.local` domain are left alone. No per-site configuration is needed: the host comes from the request.

To accept another tunnel domain, add its suffix to `$studio_jt_suffixes` at the top of `jurassic-tube.php`.

## Install on a site

Symlink the file into the site's `mu-plugins` folder:

```bash
ln -s ~/github/studio-plugins/jurassic-tube/jurassic-tube.php ~/Studio/<site-folder>/wp-content/mu-plugins/jurassic-tube.php
```

To remove it, delete the symlink.

## Use it

1. Make sure no running Studio site has a custom domain. Studio's proxy takes ports 80 and 443 when one does, and Caddy needs 443.
2. Add the domain to `/etc/hosts`:

   ```
   127.0.0.1 <sub>.jurassic.tube
   ```

3. Start the tunnel:

   ```bash
   jurassictube -u <username> -s <sub> -h localhost:<port>
   ```

4. Start Caddy:

   ```bash
   caddy reverse-proxy --from <sub>.jurassic.tube --to localhost:<port> --internal-certs --disable-redirects
   ```

Steps 2 and 4 are an optional local speed-up; the tunnel alone also works.

## Limits

- URLs that a plugin or theme builds straight from the `WP_CONTENT_URL` or `WP_PLUGIN_URL` constants, without WordPress's URL functions, are not rewritten. If you hit one, define `WP_CONTENT_URL` for the tunnel host in the site's `wp-config.php`.
- This is a workaround. The proper fix belongs in Studio's `getSiteUrlPrependContent` (`apps/cli/lib/native-php/site-setup.ts`).
