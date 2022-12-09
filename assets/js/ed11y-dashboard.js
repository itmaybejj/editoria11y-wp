class Ed1 {
    constructor() {
        
        Ed1.wrap = document.getElementById('ed1');
        Ed1.requests = {};
        Ed1.tables = {};

        Ed1.buildRequest = function (request) {
            let q = Ed1.requests[request];
            return `${q.base}?view=${q.view}&count=${q.count}&offset=${q.offset}&sort=${q.sort}&direction=${q.direction}`;
        }

        Ed1.dashboard = async function () {
            Ed1.requests['ed1result'] = {
                base : 'dashboard',
                view : 'keys',
                count : 50,
                offset : 0,
                sort : 'count',
                direction : 'DESC',
            }
            Ed1.getByResult(Ed1.buildRequest('ed1result'));
            Ed1.requests['ed1page'] = {
                base : 'dashboard',
                view : 'pages',
                count : 50,
                offset : 0,
                sort : 'page_total',
                direction : 'DESC',
            }
            Ed1.getByPage(Ed1.buildRequest('ed1page'));
        }

        Ed1.th = function (text, hash = false, sorted = false) {
            let header = document.createElement('th');
            if (!hash) {
                header.textContent = text;
            } else {
                let sorter = Ed1.button(text, hash, sorted);
                header.insertAdjacentElement('afterbegin', sorter);
            }
            return header;
        }

        Ed1.button = function ( text, hash, sorted = false) {
            let sorter = document.createElement('button');
            sorter.textContent = text;
            sorter.setAttribute('data-ed1-action', hash);
            if ( sorted ) {
                sorter.setAttribute('aria-pressed', 'true');
                let direction = 'DESC' === sorted ? 'descending' : 'ascending';
                sorter.setAttribute('title', direction);
                sorter.setAttribute('class', direction);
            }
            return sorter;
        }

        Ed1.a = function (text, hash = false, url = false, pid = false) {
            let link = document.createElement('a');
            link.textContent = text;
            let href;
            if ( ! hash ) {
                let sep = url.indexOf('?') === -1 ? '?' : '&';
                href = url + sep + 'ed1ref=' + parseInt(pid);
            }
            href = hash ? '#' + encodeURIComponent(hash) : encodeURI(url); 
            link.setAttribute('href', href);
            return link;
        }

        Ed1.td = function (text, hash = false, url = false, pid = false) {
            let cell = document.createElement('td');
            if ( url ) {
                cell.insertAdjacentElement('afterbegin', Ed1.a(text, hash, url, pid));
            } else if ( hash ) {
                cell.insertAdjacentElement('afterbegin', Ed1.button(text, hash));
            } else {
                cell.textContent = text;
            }
            return cell;
        }

        Ed1.renderByResult = function (post, count) {
            
            if (! Ed1.tables['ed1result'] ) {
                Ed1.tables['ed1result'] = document.createElement('table');
                Ed1.tables['ed1result'].setAttribute('id', 'ed1result');
                let head = document.createElement('tr');
                head.insertAdjacentElement('beforeend', Ed1.th('Issue', 'result_key'));
                head.insertAdjacentElement('beforeend', Ed1.th('Issues found', 'count', 'DESC'));
                Ed1.tables['ed1result'].insertAdjacentElement('beforeend', head);
                Ed1.wrap.insertAdjacentElement('beforeEnd', Ed1.tables['ed1result']);
                Ed1.tables['ed1result'].querySelectorAll('th button').forEach((el) => {
                    el.addEventListener('click', function() {
                        Ed1.reSort();
                        Ed1.getByResult(Ed1.buildRequest('ed1result'));
                    });
                });
            } else {
                Ed1.tables['ed1result'].querySelectorAll('tr + tr').forEach(el => {
                    el.remove();
                })
            }
            
            if (!!post) {
                post.forEach((result) => {
                    let row = document.createElement('tr');

                        let key = Ed1.td( result['result_key'], result['result_key'] );
                        row.insertAdjacentElement('beforeend', key);

                        let pageCount = Ed1.td( result['count']);
                        row.insertAdjacentElement('beforeend', pageCount);

                    Ed1.tables['ed1result'].insertAdjacentElement('beforeend', row);
                })

            let theCount = document.createElement('p');
            theCount.textContent = count;
            Ed1.wrap.insertAdjacentElement('beforeEnd', theCount);
        }
    }
    
    // Top pages.
    Ed1.renderByPage = function (post, count) {

        if (! Ed1.tables['ed1page'] ) {
            Ed1.tables['ed1page'] = document.createElement('table');
            Ed1.tables['ed1page'].setAttribute('id', 'ed1page');
            let head = document.createElement('tr');
            head.insertAdjacentElement('beforeend', Ed1.th('Page', 'page_title'));
            head.insertAdjacentElement('beforeend', Ed1.th('Issues found', 'page_total', 'DESC'));
            head.insertAdjacentElement('beforeend', Ed1.th('type', 'entity_type'));
            head.insertAdjacentElement('beforeend', Ed1.th('Path', 'page_url'));
            Ed1.tables['ed1page'].insertAdjacentElement('beforeend', head);
            Ed1.tables['ed1page'].querySelectorAll('button').forEach((el) => {
                el.addEventListener('click', function() {
                    Ed1.reSort();
                    Ed1.getByPage(Ed1.buildRequest('ed1page'));
                });
            });
            Ed1.wrap.insertAdjacentElement('beforeEnd', Ed1.tables['ed1page']);

            let theCount = document.createElement('p');
            theCount.textContent = count;
            Ed1.wrap.insertAdjacentElement('beforeEnd', theCount);
        } else {
            Ed1.tables['ed1page'].querySelectorAll('tr + tr').forEach(el => {
                el.remove();
            })
        }

        if (!!post) {
            post.forEach((result) => {
                let row = document.createElement('tr');
                    
                let pageLink = Ed1.td( result['page_title'], false, result['page_url'], result['pid'] );
                row.insertAdjacentElement('beforeend', pageLink);

                let pageCount = Ed1.td( result['page_total']);
                row.insertAdjacentElement('beforeend', pageCount);

                let type = Ed1.td( result['entity_type'] );
                row.insertAdjacentElement('beforeend', type);

                let path = result['page_url'].replace(window.location.protocol + '//' + window.location.host, ''); 
                path = Ed1.td( path );
                row.insertAdjacentElement('beforeend', path);

                Ed1.tables['ed1page'].insertAdjacentElement('beforeend', row);
            })
            }
        }

        Ed1.api = {
            method: "GET",
            headers:{
                'Content-Type': 'application/json',
                'accept': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce,
            }
        };

        Ed1.readyTriggers = function() {
            document.querySelectorAll('#ed1 button');
        }

        Ed1.getByPage = async function (action) {
            fetch(wpApiSettings.root  + 'ed11y/v1/' + action, Ed1.api,
            ).then(function(response){
                return response.json();
            }).then(function(post){
                if (post?.data?.status === 500) {
                    console.error(post.data.status + ': ' +post.message);
                } else {
                    Ed1.renderByPage(post[0], post[1]);
                }
            });
        }

        Ed1.reSort = function(event) {
            let el = document.activeElement;
            let table = el.closest('table');
            let req = table.getAttribute('id');
            Ed1.requests[req]['sort'] = el.getAttribute('data-ed1-action');
            let sort = 'descending' == el.getAttribute('class') ? 'ASC' : 'DESC';
            Ed1.requests[req]['direction'] = sort;
            let siblings = el.closest('tr').querySelectorAll('button');
            siblings.forEach(btn => {
                btn.removeAttribute('aria-pressed');
                btn.classList.remove('ascending', 'descending');
            })
            el.setAttribute('aria-pressed', 'true');
            el.classList.add(sort === 'ASC' ? 'ascending' : 'descending');
        }

        Ed1.getByResult = async function (action) {
            fetch(wpApiSettings.root  + 'ed11y/v1/' + action, Ed1.api,
            ).then(function(response){
                return response.json();
            }).then(function(post){
                if (post?.data?.status === 500) {
                    console.error(post.data.status + ': ' +post.message);
                } else {
                    console.log(post);
                    Ed1.renderByResult(post[0], post[1]);
                }
            });
        }


    }

    
}

new Ed1();
Ed1.dashboard();


