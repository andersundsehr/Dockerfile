import pytest
import stat
import re

@pytest.mark.docker_loop()
@pytest.mark.docker_images('webdevops/liquibase')
def test_liquibase_cmd(host):
    assert host.file('/usr/local/bin/liquibase').exists
    assert host.file('/usr/local/bin/liquibase').mode == 0o777

    cmd = host.run("liquibase --version")
    assert cmd.rc == 0
    assert 'Liquibase Version:' in cmd.stderr

