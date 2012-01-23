#!/bin/sh

#path to nconvert
_dirname=$(dirname $0)

#default image box size
_size=${2:-500}

$_dirname/NConvert/nconvert -v -merge_alpha -transpcolor 255 255 255 -overwrite -D -out jpeg -truecolors -rmeta -q 85 -rflag decr -ratio -resize $_size $_size\ -o $1 2>&1
