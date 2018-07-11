#Change ownership to appropriate user:group
chown -R ${GLOBAL_OWNER}:${GLOBAL_GROUP} ${GLOBAL_TARGETROOT}

#Change permissions to proper permissions
chmod -R 755 ${GLOBAL_TARGETROOT}