#!/bin/bash
step=2
for(( i=0; i<60; i=(i+$step) ))
do
   curl 'http://sdk.23yxm.com.com/index.php?g=api&m=pay&a=reSend'
   sleep $step
done

