#!/bin/bash

# This is a simple script to pull down the specified version of editoria11y from github

GIT_REF="main"

mkdir -p tmp/
cd tmp/
git clone git@github.com:itmaybejj/editoria11y.git .
git checkout $GIT_REF
rm ../assets/src/editoria11y.min.js
rm ../assets/src/editoria11y.min.js.map
mv dist/editoria11y.min.js ../assets/src/
mv dist/editoria11y.min.js.map ../assets/src/
cd ../
rm -rf tmp
