"""Publish the Git release tree to the WordPress.org SVN repository."""

import mimetypes
import os
import re
import shutil
import subprocess
import tarfile
from pathlib import Path
from tempfile import TemporaryDirectory
from xml.etree import ElementTree

SVN_URL = "https://plugins.svn.wordpress.org/nomad-site-audit"
SVN_USERNAME = "costibotez"
ROOT = Path(__file__).resolve().parents[2]


def run(*args, **kwargs):
    return subprocess.run(args, cwd=ROOT, check=True, **kwargs)


def svn(*args, password="", **kwargs):
    options = ["--non-interactive", "--no-auth-cache"]
    if password:
        options.extend(("--username", SVN_USERNAME, "--password-from-stdin"))
        kwargs["input"] = password + "\n"
    return run("svn", *options, *args, text=True, **kwargs)


def export_plugin(work):
    tar_path = work / "plugin.tar"
    build = work / "plugin"
    build.mkdir()
    run("git", "archive", "--format=tar", "-o", tar_path, "HEAD")
    with tarfile.open(tar_path) as archive:
        archive.extractall(build, filter="data")

    plugin = (build / "nomad-site-audit.php").read_text()
    readme = (build / "readme.txt").read_text()
    header = re.search(r"^\s*\* Version:[ \t]*([0-9]+(?:\.[0-9]+)+)[ \t]*$", plugin, re.M)
    stable_tag = re.search(r"^Stable tag:[ \t]*([^\s]+)[ \t]*$", readme, re.M)
    runtime = re.search(r"define\( 'NOMADSA_VERSION', '([^']+)' \);", plugin)
    if not header or not stable_tag or not runtime or len({header[1], stable_tag[1], runtime[1]}) != 1:
        raise SystemExit("The plugin header, runtime version and readme Stable tag must match.")

    for file in sorted(build.rglob("*.php")):
        run("php", "-l", file, stdout=subprocess.DEVNULL)
    return build, header[1]


def remove_missing(path):
    status = svn("status", "--xml", path, capture_output=True).stdout
    missing = {
        Path(entry.attrib["path"])
        for entry in ElementTree.fromstring(status).iter("entry")
        if entry.find("wc-status").get("item") == "missing"
    }
    for removed in sorted(missing):
        if missing.isdisjoint(removed.parents):
            svn("delete", "--force", removed)


def copy_assets(assets):
    entries = run("git", "ls-tree", "-rz", "--name-only", "HEAD", "--", ".wordpress-org/", capture_output=True).stdout
    for entry in entries.decode().split("\0"):
        asset = Path(entry)
        mime_type = mimetypes.guess_type(asset.name)[0]
        if asset.parent != Path(".wordpress-org") or not mime_type or not mime_type.startswith("image/"):
            continue
        target = assets / asset.name
        target.write_bytes(run("git", "show", f"HEAD:{entry}", capture_output=True).stdout)
        svn("add", "--force", target)
        svn("propset", "svn:mime-type", mime_type, target)


def runtime_files(directory):
    return {
        file.relative_to(directory).as_posix(): file.read_bytes()
        for file in directory.rglob("*")
        if file.is_file() and file.relative_to(directory).as_posix() not in {"README.md", "readme.txt"}
    }


def publish(repository, password, work):
    build, version = export_plugin(work)
    revision = run("git", "rev-parse", "--short", "HEAD", text=True, capture_output=True).stdout.strip()

    checkout = work / "svn"
    svn("checkout", "--depth", "immediates", repository, checkout, password=password)
    trunk = checkout / "trunk"
    assets = checkout / "assets"
    tags = checkout / "tags"
    release = tags / version
    svn("update", "--set-depth", "infinity", trunk, assets, password=password)
    svn("update", "--set-depth", "immediates", tags, password=password)

    run("rsync", "-a", "--checksum", "--delete", f"{build}/", f"{trunk}/")
    remove_missing(trunk)
    svn("add", "--force", "--no-ignore", trunk)
    copy_assets(assets)

    if not release.is_dir():
        svn("copy", trunk, release)
        print(f"Prepared WordPress.org release {version}.", flush=True)
    else:
        svn("update", "--set-depth", "infinity", release, password=password)
        if runtime_files(build) == runtime_files(release):
            readme = release / "readme.txt"
            shutil.copyfile(build / "readme.txt", readme)
            svn("add", "--force", readme)
        else:
            print(f"Version {version} is already tagged. Bump the version to publish changed plugin code.", flush=True)

    if svn("status", "--quiet", checkout, capture_output=True).stdout.strip():
        svn("commit", "-m", f"Sync Nomad Site Audit {version} from GitHub {revision}", checkout, password=password)
    else:
        print("WordPress.org SVN is already up to date.", flush=True)


def main():
    repository = os.environ.get("WORDPRESS_SVN_URL", SVN_URL)
    if repository != SVN_URL and not repository.startswith("file:///"):
        raise SystemExit("The SVN URL must be the plugin repository or a local file URL for testing.")
    password = os.environ.get("SVN_PASSWORD", "") if repository == SVN_URL else ""
    if repository == SVN_URL and not password:
        raise SystemExit("Add the WordPress.org SVN-specific password as the GitHub Actions secret SVN_PASSWORD.")
    with TemporaryDirectory(prefix="nomadsa-svn-") as path:
        publish(repository, password, Path(path))


if __name__ == "__main__":
    main()
