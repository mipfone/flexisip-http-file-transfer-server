OUTPUT_DIR = ${CURDIR}
GIT_DESCRIBE = $(shell sh -c "git describe --long" 2>/dev/null)

DESCRIBE_PARTS = $(subst -, ,$(GIT_DESCRIBE))
VERSION_TAG = $(word 1,$(DESCRIBE_PARTS))
STATUS_TAG = $(word 2,$(DESCRIBE_PARTS))
STATUS_DISTANCE_TAG = $(word 3,$(DESCRIBE_PARTS))
COMMIT_HASH_TAG = $(word 4,$(DESCRIBE_PARTS))
CLEAN_COMMIT_HASH_TAG = $(COMMIT_HASH_TAG:g%=%)

package-semvers:
	mkdir -p build
	cp flexisip-http-file-transfer-server.spec flexisip-http-file-transfer-server.spec.run
	sed -i 's/MAKE_FILE_VERSION_SEARCH/$(VERSION_TAG)/g' $(CURDIR)/flexisip-http-file-transfer-server.spec.run

ifneq (,$(filter alpha beta,$(STATUS_TAG)))
	sed -i 's/MAKE_FILE_BUILD_NUMBER_SEARCH/0.$(STATUS_TAG).$(STATUS_DISTANCE_TAG)+$(CLEAN_COMMIT_HASH_TAG)/g' $(CURDIR)/flexisip-http-file-transfer-server.spec.run
else
	sed -i 's/MAKE_FILE_BUILD_NUMBER_SEARCH/1/g' $(CURDIR)/flexisip-http-file-transfer-server.spec.run
endif

cleanup-package-semvers:
	rm flexisip-http-file-transfer-server.spec.run

package-common:
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
	cp flexisip-http-file-transfer-server.spec.run $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec

	tar cvf flexisip-http-file-transfer-server.tar.gz -C $(OUTPUT_DIR) flexisip-http-file-transfer-server
	mv flexisip-http-file-transfer-server.tar.gz $(OUTPUT_DIR)/rpmbuild/SOURCES/flexisip-http-file-transfer-server.tar.gz

package-end-common:
	rm -rf $(OUTPUT_DIR)/flexisip-http-file-transfer-server
	rm -rf $(OUTPUT_DIR)/rpmbuild/SPECS $(OUTPUT_DIR)/rpmbuild/SOURCES $(OUTPUT_DIR)/rpmbuild/SRPMS $(OUTPUT_DIR)/rpmbuild/BUILD $(OUTPUT_DIR)/rpmbuild/BUILDROOT

rpm-only:
	rpmbuild -v -bb --define 'dist .el8' --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmdir $(OUTPUT_DIR)/rpmbuild" $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec

	@echo "📦✅ RPM Package Created"

	@echo "🧹 Cleanup"
	mv rpmbuild/*/*.rpm build/.
	rm -r rpmbuild

rpm-el7-only:
	rpmbuild -v -bb --define 'dist .el7' --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmdir $(OUTPUT_DIR)/rpmbuild" $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec

	@echo "📦✅ RPM Package Created"

	@echo "🧹 Cleanup"
	mv rpmbuild/*/*.rpm build/.
	rm -r rpmbuild

rpm-el9-only:
	rpmbuild -v -bb --define 'dist .el9' --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmdir $(OUTPUT_DIR)/rpmbuild" $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec

	@echo "📦✅ RPM Package Created"

	@echo "🧹 Cleanup"
	mv rpmbuild/*/*.rpm build/.
	rm -r rpmbuild

deb-only:
	rpmbuild -v -bb --with deb --define '_topdir $(OUTPUT_DIR)/rpmbuild' --define "_rpmfilename tmp.rpm" --define "_rpmdir $(OUTPUT_DIR)/rpmbuild" $(OUTPUT_DIR)/rpmbuild/SPECS/flexisip-http-file-transfer-server.spec
	fakeroot alien -g -k --scripts $(OUTPUT_DIR)/rpmbuild/tmp.rpm
	rm -r $(OUTPUT_DIR)/rpmbuild
	rm -rf $(OUTPUT_DIR)/*.orig
	sed -i 's/Depends:.*/Depends: $${shlibs:Depends}, php, php-xml, php-mysql/g' $(OUTPUT_DIR)/bc-flexisip-http-file-transfer-server*/debian/control

	cd `ls -rt $(OUTPUT_DIR) | tail -1` && dpkg-buildpackage --no-sign
	@echo "📦✅ DEB Package Created"

	@echo "🧹 Cleanup"
	ls -d */ | cut -f1 -d'/' | grep bc-flexisip-http-file-transfer-server | xargs rm -rf
	rm -rf flexisip-http-file-transfer-server
	ls bc-flexisip-http-file-transfer-server* | grep -v "\.deb" | xargs rm

	mv *.deb build/.

rpm-el7: package-semvers package-common rpm-el7-only cleanup-package-semvers package-end-common
rpm-el9: package-semvers package-common rpm-el9-only cleanup-package-semvers package-end-common
rpm: package-semvers package-common rpm-only cleanup-package-semvers package-end-common
deb: package-semvers package-common deb-only cleanup-package-semvers package-end-common

.PHONY: all
