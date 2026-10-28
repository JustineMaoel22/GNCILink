"""Check Apache routing using real local hosts, without a production Host override."""
import urllib.error
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
for origin in ['http://localhost:8080', 'http://127.0.0.1:8080']:
    for path, status, contains in [
        ('/pages/academics/college/college-department/cased', 200, 'College of Arts'),
        ('/pages/academics/college/program/beed', 200, 'Bachelor of Elementary Education'),
        ('/pages/academics/college/college-dept', 200, 'College Departments'),
        ('/pages/academics/college/college-department.php?slug=cased&ce_preview=1', 302,
         '/pages/academics/college/college-department/cased?ce_preview=1'),
        ('/pages/academics/college/program?slug=beed', 302,
         '/pages/academics/college/program/beed'),
        ('/pages/academics/college/college-department/definitely-missing-department', 404,
         'Department Not Found'),
    ]:
        try:
            response = opener.open(origin + path, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = response.read().decode('utf-8', errors='replace')
            assert response.status == status, (origin, path, response.status)
            if status == 302:
                assert response.headers.get('Location') == contains, (path, response.headers.get('Location'))
            else:
                assert contains in body and '<footer' in body, path
        print('PASS:', origin + path, status)
