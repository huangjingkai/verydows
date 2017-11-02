#!/bin/sh

APP_ROOT=$(cd `dirname $0`; pwd)

#java config
if[-z $JAVA_HOME]; then
	JAVA=$JAVA_HOME/bin/java
else
	JAVA=java
fi

#run manager
JAVA -jar ${APP_ROOT}/packages/xxx.jar $1

#run score
