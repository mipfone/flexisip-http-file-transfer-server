$(eval GIT_DESCRIBE = $(shell sh -c "git describe"))
OUTPUT_DIR = ${CURDIR}
rpm:
	rm -rf $(OUTPUT_DIR)/http-file-transfer-server
	mkdir $(OUTPUT_DIR)/http-file-transfer-server
	mkdir -p $(OUTPUT_DIR)/rpmbuild/SPECS
	mkdir -p $(OUTPUT_DIR)/rpmbuild/SOURCES
	cp src/*.php $(OUTPUT_DIR)/http-file-transfer-server/
	cp README.md $(OUTPUT_DIR)/http-file-transfer-server/
	mkdir -p $(OUTPUT_DIR)/http-file-transfer-server/httpd
	cp httpd/http-file-transfer-server.conf $(OUTPUT_DIR)/http-file-transfer-server/httpd
	cp http-file-transfer-server.spec $(OUTPUT_DIR)/rpmbuild/SPECS/
	tar cvf http-file-transfer-server.tar.gz -C $(OUTPUT_DIR) http-file-transfer-server
	mv http-file-transfer-server.tar.gz $(OUTPUT_DIR)/rpmbuild/SOURCES/http-file-transfer-server.tar.gz
	rpmbuild -v -bb  --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmdir $(OUTPUT_DIR)/rpmbuild" $(OUTPUT_DIR)/rpmbuild/SPECS/http-file-transfer-server.spec
	rm -rf $(OUTPUT_DIR)/http-file-transfer-server

.PHONY: rpm
