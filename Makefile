$(eval GIT_DESCRIBE = $(shell sh -c "git describe"))
OUTPUT_DIR = ${CURDIR}
rpm: prepare
	rpmbuild -v -bb  --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmdir $(OUTPUT_DIR)/rpmbuild/RPMS" $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec
	rm -rf $(OUTPUT_DIR)/flexisip-http-file-transfer-server

prepare:
	rm -rf $(OUTPUT_DIR)/flexisip-http-file-transfer-server
	mkdir $(OUTPUT_DIR)/flexisip-http-file-transfer-server
	mkdir -p $(OUTPUT_DIR)/rpmbuild/SPECS
	mkdir -p $(OUTPUT_DIR)/rpmbuild/SOURCES
	cp src/*.php $(OUTPUT_DIR)/flexisip-http-file-transfer-server/
	cp src/*.conf $(OUTPUT_DIR)/flexisip-http-file-transfer-server/
	cp README.md $(OUTPUT_DIR)/flexisip-http-file-transfer-server/
	cp LICENSE.txt $(OUTPUT_DIR)/flexisip-http-file-transfer-server/
	mkdir -p $(OUTPUT_DIR)/flexisip-http-file-transfer-server/httpd
	cp httpd/flexisip-http-file-transfer-server.conf $(OUTPUT_DIR)/flexisip-http-file-transfer-server/httpd
	mkdir -p $(OUTPUT_DIR)/flexisip-http-file-transfer-server/logrotate
	cp logrotate/flexisip-http-file-transfer-server.conf $(OUTPUT_DIR)/flexisip-http-file-transfer-server/logrotate
	mkdir -p $(OUTPUT_DIR)/flexisip-http-file-transfer-server/cron.d
	cp cron.d/flexisip-http-file-transfer-server $(OUTPUT_DIR)/flexisip-http-file-transfer-server/cron.d
	cp flexisip-http-file-transfer-server.spec $(OUTPUT_DIR)/rpmbuild/SPECS/
	tar cvf flexisip-http-file-transfer-server.tar.gz -C $(OUTPUT_DIR) flexisip-http-file-transfer-server
	mv flexisip-http-file-transfer-server.tar.gz $(OUTPUT_DIR)/rpmbuild/SOURCES/flexisip-http-file-transfer-server.tar.gz

deb: prepare
	rpmbuild -v -bb  --define "dist .deb" --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmdir $(OUTPUT_DIR)/rpmbuild/DEBS" $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec
	rm -rf $(OUTPUT_DIR)/flexisip-http-file-transfer-server
	cd $(OUTPUT_DIR)/rpmbuild/DEBS/x86_64/; alien -d -k *.rpm;

.PHONY: all
