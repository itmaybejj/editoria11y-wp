class Ed1 {
    constructor() {

        Ed1.defaults = function () {
            let queryString = window.location.search;
            let urlParams = new URLSearchParams(queryString);
            Ed1.url = '//' + window.location.host + window.location.pathname + '?';
            if (urlParams.get('page')) {
                Ed1.url += 'page=' + urlParams.get('page') + '&';
            }
            // Todo build a function to append dynamically applicable values so we can multifilter.

            let resultOffset = urlParams.get('roff');
            resultOffset = !isNaN(resultOffset) ? +resultOffset : 0;
            let resultSort = urlParams.get('offset');
            resultSort = !!resultSort ? resultSort : 'count';
            let resultDir = urlParams.get('rdir');
            resultDir = resultDir === 'DESC' || resultDir === 'ASC' ? resultDir : 'DESC';
            Ed1.resultKey = urlParams.get('rkey');
            Ed1.resultKey = !!Ed1.resultKey ? Ed1.resultKey : false;

            let pageOffset = urlParams.get('poff');
            pageOffset = !isNaN(pageOffset) ? +pageOffset : 0;
            let pageSort = urlParams.get('poff');
            pageSort = !!pageSort ? pageSort : 'page_total';
            let pageDir = urlParams.get('pdir');
            pageDir = pageDir === 'DESC' || pageDir === 'ASC' ? pageDir : 'DESC';
            Ed1.type = urlParams.get('type');

            Ed1.requests['ed1result'] = {
                base: 'dashboard',
                view: 'keys',
                count: 50,
                offset: resultOffset,
                sort: resultSort,
                direction: resultDir,
                result_key: Ed1.resultKey,
                entity_type: Ed1.type,
            }
            Ed1.requests['ed1page'] = {
                base: 'dashboard',
                view: 'pages',
                count: 50,
                offset: pageOffset,
                sort: pageSort,
                direction: pageDir,
                result_key: Ed1.resultKey,
                entity_type: Ed1.type,
            }
        }
        Ed1.buildRequest = function (request) {
            let q = Ed1.requests[request];
            let req = `${q.base}?view=${q.view}&count=${q.count}&offset=${q.offset}&sort=${q.sort}&direction=${q.direction}&result_key=${q.result_key}&entity_type=${q.entity_type}`;
            console.log(req);
            return req;
        }
        Ed1.init = async function () {
            // Get results with default params
            Ed1.requests = {};
            Ed1.tables = {};
            Ed1.defaults();
            Ed1.wrapPage = document.getElementById('ed1-page-wrapper');
            Ed1.wrapResults = document.getElementById('ed1-results-wrapper');
            Ed1.get.ed1page(Ed1.buildRequest('ed1page'), false);
            if ( !!Ed1.resultKey || !!Ed1.type ) {
                let h1 = document.querySelector('#ed1 h1');
                let filters = Ed1.resultKey ? ' with ' + Ed1.resultKey + ' issues' : false;
                filters = Ed1.type ? filters ? filters + ' of type ' + Ed1.type : 'of type ' + Ed1.type : filters;
                h1.textContent = `Pages ${filters}`;
                let reset = Ed1.render.a('View all pages', false, Ed1.url);
                h1.insertAdjacentElement('afterend', reset);
            } else {
                Ed1.get.ed1result(Ed1.buildRequest('ed1result'), false);
            }
        }
        Ed1.announce = function(string) {
            if (!Ed1.liveRegion) {
                Ed1.liveRegion = document.createElement('div');
                Ed1.liveRegion.setAttribute('class', 'visually-hidden');
                Ed1.liveRegion.setAttribute('aria-live', 'polite');
                document.getElementById('ed1').insertAdjacentElement('beforeend', Ed1.liveRegion);
            }
            Ed1.liveRegion.textContent = '';
            window.setTimeout(function() {
                Ed1.liveRegion.textContent = string;
            },1500);
        } 

        /**
         * 
         * Builder functions to quickly assemble HTML elements.
         * @param {*} text 
         * @param {*} hash 
         * @param {*} sorted 
         * @returns th
         */
        Ed1.render = {};
        Ed1.render.th = function (text, hash = false, sorted = false) {
            let header = document.createElement('th');
            if (!hash) {
                header.textContent = text;
            } else {
                let sorter = Ed1.render.button(text, hash, sorted);
                header.insertAdjacentElement('afterbegin', sorter);
            }
            return header;
        }
        Ed1.render.button = function (text, hash, sorted = false) {
            let sorter = document.createElement('button');
            sorter.textContent = text;
            sorter.setAttribute('data-ed1-action', hash);
            if (sorted) {
                sorter.setAttribute('aria-pressed', 'true');
                let direction = 'DESC' === sorted ? 'descending' : 'ascending';
                sorter.setAttribute('title', direction);
                sorter.setAttribute('class', direction);
            }
            return sorter;
        }
        Ed1.render.a = function (text, hash = false, url = false, pid = false) {
            let link = document.createElement('a');
            link.textContent = text;
            let href;
            if (!hash) {
                let sep = url.indexOf('?') === -1 ? '?' : '&';
                href = url + sep + 'ed1ref=' + parseInt(pid);
            }
            href = hash ? '#' + encodeURIComponent(hash) : encodeURI(url);
            link.setAttribute('href', href);
            return link;
        }
        Ed1.render.td = function (text, hash = false, url = false, pid = false, cls = false) {
            let cell = document.createElement('td');
            if (url) {
                cell.insertAdjacentElement('afterbegin', Ed1.render.a(text, hash, url, pid));
            } else if (hash) {
                cell.insertAdjacentElement('afterbegin', Ed1.render.button(text, hash));
            } else {
                cell.textContent = text;
            }
            if (cls) {
                cell.setAttribute('class', cls);
            }
            return cell;
        }
        Ed1.render.details = function (text, id) {
            let details = document.createElement('details');
            details.setAttribute('open', '');
            let summary = document.createElement('summary');
            summary.textContent = text;
            summary.setAttribute('id', id);
            details.append(summary);
            return details;
        }
        /**
         * Hat tip to https://webdesign.tutsplus.com/tutorials/pagination-with-vanilla-javascript--cms-41896
         * @param {*} after 
         * @param {*} rows 
         * @param {*} perPage 
         * @param {*} offset 
         * @param {*} labelId 
         * @returns 
         */
        Ed1.render.pagination = function (after, rows, perPage, offset, labelId = false) {
            if (rows < perPage) {
                return false;
            }

            let pageWrap = document.createElement('nav');
            if (labelId) {
                pageWrap.setAttribute('aria-labelledby', labelId);
            }

            let appendPageNumber = (index, current = false) => {
                let pageNumber = document.createElement('button');
                pageNumber.className = 'pagination-number';
                pageNumber.innerHTML = index;
                pageNumber.setAttribute('page-index', index);
                pageNumber.setAttribute('aria-label', 'Page ' + index);
                if (current) {
                    pageNumber.setAttribute('aria-current', 'page');
                }

                pageWrap.appendChild(pageNumber);
            };

            let pageCount = Math.ceil(rows / perPage);
            let activePage = Math.ceil(offset / perPage) + 1;
            for (let i = 1; i <= pageCount; i++) {
                let current = i === activePage;
                appendPageNumber(i, current);
            }

            Ed1.tables[after].insertAdjacentElement('afterend', pageWrap);

            pageWrap.querySelectorAll('button').forEach((button) => {
                const pageIndex = Number(button.getAttribute("page-index"));

                if (pageIndex) {
                    button.addEventListener("click", (e) => {
                        Ed1.setPage(e, after, (pageIndex - 1) * perPage);
                    });
                }
            });
        }

        Ed1.setPage = function(e, table, offset) {
            e.target.closest('nav').querySelector('[aria-current]').removeAttribute('aria-current');
            Ed1.requests[table]['offset'] = offset;
            Ed1.get[table](Ed1.buildRequest(table), true);
            e.target.setAttribute('aria-current', 'page');
        }

        Ed1.readyTriggers = function () {
            document.querySelectorAll('#ed1 button');
        }

        /**
         * Renderer for viewing results by test name.
         * @param {*} post 
         * @param {*} count 
         */
        Ed1.render.ed1result = function (post, count, announce) {

            if (!Ed1.tables['ed1result']) {
                Ed1.tables['ed1result'] = document.createElement('table');
                Ed1.tables['ed1result'].setAttribute('id', 'ed1result');
                let head = document.createElement('tr');
                head.insertAdjacentElement('beforeend', Ed1.render.th('Issue', 'result_key'));
                head.insertAdjacentElement('beforeend', Ed1.render.th('Pages', 'count', 'DESC'));
                Ed1.tables['ed1result'].insertAdjacentElement('beforeend', head);
                let tableDetails = Ed1.render.details('Issues by Type', 'ed1result-title')
                Ed1.wrapResults.append(tableDetails);
                tableDetails.append(Ed1.tables['ed1result']);
                Ed1.tables['ed1result'].querySelectorAll('th button').forEach((el) => {
                    el.addEventListener('click', function () {
                        Ed1.reSort();
                        Ed1.get.ed1result(Ed1.buildRequest('ed1result'));
                    });
                });
                //count
                Ed1.render.pagination('ed1result', count, 50, 0, 'ed1result-title');
            } else {
                Ed1.tables['ed1result'].querySelectorAll('tr + tr').forEach(el => {
                    el.remove();
                })
            }

            if (!!post) {
                post.forEach((result) => {
                    let row = document.createElement('tr');

                    let key = Ed1.render.td(result['result_key'], false, Ed1.url + 'rkey=' + encodeURIComponent(result['result_key']), false, 'rkey');
                    row.insertAdjacentElement('beforeend', key);

                    let pageCount = Ed1.render.td(result['count']);
                    row.insertAdjacentElement('beforeend', pageCount);

                    Ed1.tables['ed1result'].insertAdjacentElement('beforeend', row);
                })
            }

            if (announce) {
                Ed1.announce(post.length + " results");
            }
        }

        /**
         * Renderer for viewing results by page.
         * @param {*} post 
         * @param {*} count 
         */
        Ed1.render.ed1page = function (post, count, announce) {

            if (!Ed1.tables['ed1page']) {
                Ed1.tables['ed1page'] = document.createElement('table');
                Ed1.tables['ed1page'].setAttribute('id', 'ed1page');
                let head = document.createElement('tr');
                head.insertAdjacentElement('beforeend', Ed1.render.th('Page', 'page_title'));
                head.insertAdjacentElement('beforeend', Ed1.render.th('Issues on page', 'page_total', 'DESC'));
                head.insertAdjacentElement('beforeend', Ed1.render.th('type', 'entity_type'));
                head.insertAdjacentElement('beforeend', Ed1.render.th('Path', 'page_url'));
                Ed1.tables['ed1page'].insertAdjacentElement('beforeend', head);
                let tableDetails = Ed1.render.details('Pages with issues', 'ed1page-title')
                Ed1.wrapPage.append(tableDetails);
                tableDetails.append(Ed1.tables['ed1page']);
                Ed1.tables['ed1page'].querySelectorAll('button').forEach((el) => {
                    el.addEventListener('click', function () {
                        Ed1.reSort();
                        Ed1.get.ed1page(Ed1.buildRequest('ed1page'));
                    });
                });
                Ed1.render.pagination('ed1page', count, 50, 0, 'ed1page-title');

            } else {
                Ed1.tables['ed1page'].querySelectorAll('tr + tr').forEach(el => {
                    el.remove();
                })
            }

            if (!!post) {
                post.forEach((result) => {
                    let row = document.createElement('tr');

                    let pageLink = Ed1.render.td(result['page_title'], false, result['page_url'], result['pid']);
                    row.insertAdjacentElement('beforeend', pageLink);

                    let pageCount = Ed1.render.td(result['page_total']);
                    row.insertAdjacentElement('beforeend', pageCount);

                    let type = Ed1.render.td(result['entity_type'], false, `${Ed1.url}type=${encodeURIComponent(result['entity_type'])}`);
                    row.insertAdjacentElement('beforeend', type);

                    let path = result['page_url'].replace(window.location.protocol + '//' + window.location.host, '');
                    path = Ed1.render.td(path);
                    row.insertAdjacentElement('beforeend', path);

                    Ed1.tables['ed1page'].insertAdjacentElement('beforeend', row);
                })
            }

            if (announce) {
                Ed1.announce(post.length + " results");
            }
        }

        /**
         * API calls.
         */
        Ed1.api = {
            method: "GET",
            headers: {
                'Content-Type': 'application/json',
                'accept': 'application/json',
                'X-WP-Nonce': wpApiSettings.nonce,
            }
        };

        Ed1.get = {};
        Ed1.get.ed1page = async function (action, announce = false) {
            fetch(wpApiSettings.root + 'ed11y/v1/' + action, Ed1.api,
            ).then(function (response) {
                return response.json();
            }).then(function (post) {
                if (post?.data?.status === 500) {
                    console.error(post.data.status + ': ' + post.message);
                } else {
                    Ed1.render.ed1page(post[0], post[1], announce);
                }
            });
        }
        Ed1.get.ed1result = async function (action, announce = false) {
            fetch(wpApiSettings.root + 'ed11y/v1/' + action, Ed1.api,
            ).then(function (response) {
                return response.json();
            }).then(function (post) {
                if (post?.data?.status === 500) {
                    console.error(post.data.status + ': ' + post.message);
                } else {
                    Ed1.render.ed1result(post[0], post[1], announce);
                }
            });
        }

        /**
         * User Interactions.
         */
        Ed1.reSort = function (event) {
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


    }


}

new Ed1();
Ed1.init();


