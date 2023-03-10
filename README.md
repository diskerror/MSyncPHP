# MSync

Ever upload a file to the server and overwrite someone else's changes. Right, shouldn't happen. Proper project organizing and communication protocols should be clear and enforced.

But this can still happen with products like [SuiteCRM](https://suitecrm.com) where code is generated by other parts of the package and not just for caching. A "cache/" directory being a well defined and known place where files will often be overwritten. Frontend users might have no idea that these code changes are happening within the web-root directory.

MSync performs the same as simple usage of `rsync` but adds a manifest file. That file holds the state of the desired files at the time of last sync. The remote connection is handled by [phpseclib](https://phpseclib.com). The status of both the remote and local directories are compared against the manifest. File differences are detected with hash strings generated from the remote and local contents of code text files. Graphics files are uploaded or downloaded only if their sizes or mod times differ.

There are three different regex strings generated from the users preferences. One is used to block files from upload and download. This is intended to prevent MSync from seeing business data files at all such as PDF, MS Word documents, and log files stored within the web-root. The second is used to prevent hashing of the files. The default values refer to a number of graphics file formats. The third regex string is used for so that some files are only pulled from the remote directory. These might be cached executable script files that might be necessary for step debugging. Any changes to those files might have unpredictable affect on the running site.

## Help:
```
./msync -H
```
or read the file “help_screen.txt” for what’s planned.
