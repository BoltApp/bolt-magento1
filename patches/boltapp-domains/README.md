# bolt.com to boltapp.com patches

Bolt is moving the hosts this plugin talks to at runtime from `bolt.com` to
`boltapp.com`. No Magento 1 release carries that change yet, so these patches are
the way to make the move on an installed store. Each one applies to exactly one
plugin version and changes nothing else.

## Pick the patch matching your installed version

Check which version you are on. In the Magento admin it is shown under
**System > Configuration > Payment Methods > Bolt Pay**, or read it from the
module config:

```bash
grep -m1 '<version>' app/code/community/Bolt/Boltpay/etc/config.xml
```

Then take `bolt-magento1-<your-version>-boltapp-domains.patch` from this directory.
Patches are available for **2.9.0** and **2.10.0**.

A patch only applies to its own version. Applying the 2.10.0 patch to a 2.9.0
install fails rather than half-applying.

## Applying it

The plugin lives directly in your Magento tree (`app/code/community/Bolt/Boltpay/`,
`js/boltpay/`, `lib/Boltpay/`, `skin/`), and the patch paths are relative to that
root. Run it from your **Magento root directory**:

```bash
git apply bolt-magento1-2.10.0-boltapp-domains.patch
# or, without git:
patch -p1 < bolt-magento1-2.10.0-boltapp-domains.patch
```

If you installed the plugin through modman, apply it inside the module directory
instead (`.modman/bolt-magento1/`), which has the same layout, and run
`modman deploy bolt-magento1` afterwards.

The patch also updates the plugin's unit tests under `tests/`. A production store
does not ship that directory, so those hunks are skipped there; `git apply` and
`patch` both report that and apply the rest.

### After applying

Magento caches the module configuration, so the old URLs stay in use until the
cache is cleared:

```bash
rm -rf var/cache/*
# or in the admin: System > Cache Management > Flush Magento Cache
```

If you run a full-page cache or a compiler, flush and recompile those too.

## Checking it worked

```bash
grep -rnE '(api|connect|merchant)(-sandbox)?\.bolt\.com' app/code/community/Bolt/Boltpay
```

That should print nothing. In a browser, your storefront should load
`connect.boltapp.com/connect.js` and `connect.boltapp.com/track.js`, and a test
order should complete normally.

## What the patch changes

The Bolt hosts the plugin calls at runtime move to `boltapp.com`: `api`,
`api-sandbox`, `connect`, `connect-sandbox`, `merchant` and `merchant-sandbox`.
All of them are constants in `Helper/UrlTrait.php`; every request and script URL
the plugin builds goes through that helper. The admin help text that links to
`merchant.bolt.com/settings` moves with them.

On **2.10.0** the validator behind the custom URL overrides in sandbox mode
(`validateCustomUrl`) is widened to accept `boltapp.com`. Without that, a custom
Bolt URL set to a `boltapp.com` host is silently rejected and replaced with the
default. `bolt.com` and `bolt.me` hosts stay accepted. 2.9.0 has no such validator,
which is the only difference between the two patches.

Unit tests are updated alongside the code, so applying a patch to a checkout of the
repository does not leave a failing build.

### Deliberately unchanged

Copyright headers, the `dev@bolt.com` contact address, the `na@bolt.com` placeholder
the Apple Pay flow compares against (a value, not a host), `CHANGELOG.md`, and the
`docs.bolt.com` and `support.bolt.com` help links in the admin.
