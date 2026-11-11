# wp-env Jurassic Tube

A single-file mu-plugin that lets a [wp-env](https://www.npmjs.com/package/@wordpress/env) site answer on its Jurassic Tube domain while it keeps running on its usual local port.

## The problem

wp-env defines `WP_HOME` and `WP_SITEURL` as `http://localhost:<port>`. A request for `<sub>.jurassic.tube` gets a canonical redirect that keeps the tunnel host but adds the local port (`https://<sub>.jurassic.tube:<port>/`), which nothing serves.

## What the plugin does

When a request arrives on a `*.jurassic.tube` host, the plugin rewrites the home, site, plugin, content and upload URLs, and `localhost:<port>` links inside post content, onto `https://<that host>`. Requests on `localhost:<port>` and WP-CLI runs are left alone, so e2e tests that target localhost keep working.

## Install on a project

Add a mapping to the project's `.wp-env.override.json` (usually gitignored) and run `wp-env start`:

```json
{
	"mappings": {
		"wp-content/mu-plugins/wp-env-jurassic-tube.php": "/Users/<you>/github/studio-plugins/wp-env-jurassic-tube/wp-env-jurassic-tube.php"
	}
}
```

Then start the tunnel against the wp-env port:

```bash
jurassictube -u <username> -s <sub> -h localhost:<port>
```

## The config-only alternative

wp-env can serve the tunnel domain with no plugin, but only on port 80 or 443: it forces its port onto `WP_SITEURL` unless the port is one of those two.

```json
{
	"port": 80,
	"config": {
		"WP_HOME": "https://<sub>.jurassic.tube",
		"WP_SITEURL": "https://<sub>.jurassic.tube"
	}
}
```

Point the tunnel at `localhost:80`. The costs: the port must be free (Studio's proxy takes 80 and 443 when a site has a custom domain), the site no longer answers on `localhost:<port>`, and anything that targets that URL, such as e2e config, stops working. Use the plugin when those matter.

## Limits

- URLs that a plugin or theme builds straight from the `WP_CONTENT_URL` or `WP_PLUGIN_URL` constants, and are not enqueued scripts or styles, are not rewritten.
- Jetpack is not handled here; see [jurassic-tube](../jurassic-tube/README.md) for the filters it needs.
