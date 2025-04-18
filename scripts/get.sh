#!/bin/bash

# This is a simple script to pull down the specified version of editoria11y from github

GIT_REF="2.3.x"

mkdir -p tmp/
cd tmp/
git clone git@github.com:itmaybejj/editoria11y.git .
git checkout $GIT_REF
rm ../assets/lib/editoria11y.min.js
rm ../assets/lib/editoria11y.min.js.map
rm ../assets/lib/editoria11y.min.css
rm ../assets/lib/editoria11y.min.css.map
mv dist/editoria11y.min.js ../assets/lib/
mv dist/editoria11y.min.js.map ../assets/lib/
mv dist/editoria11y.min.css ../assets/lib/
mv dist/editoria11y.min.css.map ../assets/lib/

cd ../
rm -rf tmp
