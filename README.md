# Studio Plugins

Small WordPress plugins for sites running in [WordPress Studio](https://developer.wordpress.com/studio/). Each one works around a limitation of the local Studio environment and is meant for development sites, not production.

## Plugins

| Plugin | What it does |
| --- | --- |
| [jurassic-tube](jurassic-tube/README.md) | Lets a Studio site answer on its Jurassic Tube domain instead of redirecting to `localhost:<port>`, and reports that domain to Jetpack. |

## Layout

Each plugin lives in its own folder with its own README, which covers what it does, how to install it and its limits.

Plugins are single-file mu-plugins. To install one, symlink its file into the site's `mu-plugins` folder:

```bash
ln -s ~/github/studio-plugins/<plugin>/<plugin>.php ~/Studio/<site-folder>/wp-content/mu-plugins/<plugin>.php
```

To remove it, delete the symlink. A symlinked plugin picks up changes from this repo without a reinstall.
