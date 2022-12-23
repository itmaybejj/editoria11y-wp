class Ed1 {
    constructor() {

        /**
         * Gather query variables into arrays.
         * Clicking sort buttons will update arrays before
         * buildRequest assembles values into API call.
         */
        Ed1.params = function () {
            let queryString = window.location.search;
            let urlParams = new URLSearchParams(queryString);
            Ed1.url = 'https://' + window.location.host + window.location.pathname + '?';
            if (urlParams.get('page')) {
                Ed1.url += 'page=' + urlParams.get('page') + '&';
            }
            // Todo build a function to append dynamically applicable values so we can multifilter.

            // Only accept numerical offsets
            let resultOffset = urlParams.get('roff');
            resultOffset = !isNaN(resultOffset) ? +resultOffset : 0;
            let pageOffset = urlParams.get('poff');
            pageOffset = !isNaN(pageOffset) ? +pageOffset : 0;

            // Todo sanitize
            let resultSort = urlParams.get('rsort');
            resultSort = !!resultSort ? resultSort : 'count';
            let pageSort = urlParams.get('psort');
            pageSort = !!pageSort ? pageSort : 'page_total';

            // Validate sort direction
            let resultDir = urlParams.get('rdir');
            resultDir = resultDir === 'DESC' || resultDir === 'ASC' ? resultDir : 'DESC';
            let pageDir = urlParams.get('pdir');
            pageDir = pageDir === 'DESC' || pageDir === 'ASC' ? pageDir : 'DESC';
            
            // Test name to filter by; will be validated.
            Ed1.resultKey = urlParams.get('rkey');
            Ed1.resultKey = !!Ed1.resultKey ? Ed1.resultKey : false;            
            
            // Page type to filter by; will be validated.
            Ed1.type = urlParams.get('type');

            // Key arrays to be assembled into URLs on request.
            Ed1.requests = {};
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
            Ed1.requests['ed1dismiss'] = {
                base: 'dismiss',
                view: '',
                count: 50,
                offset: pageOffset,
                sort: pageSort,
                direction: pageDir,
                result_key: Ed1.resultKey,
                entity_type: Ed1.type,
            }
        }

        /**
         * Assemble request array into API call.
         * @param {*} request 
         * @returns string 
         */
        Ed1.buildRequest = function (request) {
            let q = Ed1.requests[request];
            let req = `${q.base}?view=${q.view}&count=${q.count}&offset=${q.offset}&sort=${q.sort}&direction=${q.direction}&result_key=${q.result_key}&entity_type=${q.entity_type}`;
            return req;
        }

        /**
         * Gather GET requests and make API calls.
         */
        Ed1.init = async function () {
            // Get results with default params
            
            Ed1.params();
            Ed1.tables = {};
            Ed1.wrapper = document.getElementById('ed1');
            Ed1.wrapPage = Ed1.wrapper.querySelector('#ed1-page-wrapper');
            Ed1.wrapResults = Ed1.wrapper.querySelector('#ed1-results-wrapper');
            Ed1.wrapDismiss = Ed1.wrapper.querySelector('#ed1-dismissals-wrapper');
            Ed1.render.tableHeaders();

            // Only build result table if there is no result or type filter.
            if ( !!Ed1.resultKey || !!Ed1.type ) {
                let h1 = document.querySelector('#ed1 h1');
                let filters = Ed1.resultKey ? ' with ' + Ed1.resultKey + ' issues' : false;
                filters = Ed1.type ? filters ? filters + ' of type ' + Ed1.type : 'of type ' + Ed1.type : filters;
                h1.textContent = `Pages ${filters}`;
                let reset = Ed1.render.a('View all pages', false, Ed1.url);
                h1.insertAdjacentElement('afterend', reset);
                Ed1.wrapResults.style.display = 'none';
            } else {
               Ed1.get.ed1result(Ed1.buildRequest('ed1result'), false);
            }
            
            // Always build page and dismissal tables.
            Ed1.get.ed1page(Ed1.buildRequest('ed1page'), false);

            window.setTimeout( function() {
                // Lazyload last table and reveal results.
                Ed1.get.ed1dismiss(Ed1.buildRequest('ed1dismiss'), false);
            }, 50 );
            window.setTimeout( function() {
                Ed1.show();
            }, 1000)
            window.setTimeout( function() {
                let neverLoaded = document.querySelectorAll('#ed1 .loading');
                Array.from(neverLoaded).forEach((el) => {
                    el.textContent = 'API error.'
                })
            }, 3000)
        }

        Ed1.show = function() {
            Ed1.wrapper.classList.add('show');
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
            let button = document.createElement('button');
            button.textContent = text;
            button.setAttribute('data-ed1-action', hash);
            if (sorted) {
                button.setAttribute('aria-pressed', 'true');
                let direction = 'DESC' === sorted ? 'descending' : 'ascending';
                button.setAttribute('title', direction);
                button.setAttribute('class', direction);
            }
            return button;
        }

        // Render a link with url sanitized and html encoded.
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
        Ed1.render.noResults = function (text, colspan) {
            let row = document.createElement('tr');
            let td = Ed1.render.td(text);
            td.setAttribute('colspan', colspan);
            row.append(td);
            return row;
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

        Ed1.render.tableHeaders = function () {
            Ed1.tables['ed1result'] = document.createElement('table');
            Ed1.tables['ed1result'].setAttribute('id', 'ed1result');
            let head = document.createElement('tr');
            head.insertAdjacentElement('beforeend', Ed1.render.th('Issue', 'result_key'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('Pages', 'count', 'DESC'));
            Ed1.tables['ed1result'].insertAdjacentElement('beforeend', head);
            let tableDetails = Ed1.render.details('Issues by Type', 'ed1result-title')
            Ed1.wrapResults.append(tableDetails);
            let loadWrap = document.createElement('tr');
            let loading = Ed1.render.td('loading...', false, false, false, 'loading');
            loading.setAttribute('colspan', '6');
            loadWrap.append(loading);
            Ed1.tables['ed1result'].append(loadWrap);
            tableDetails.append(Ed1.tables['ed1result']);
            Ed1.tables['ed1result'].querySelectorAll('th button').forEach((el) => {
                el.addEventListener('click', function () {
                    Ed1.reSort();
                    Ed1.get.ed1result(Ed1.buildRequest('ed1result'));
                });
            });

            Ed1.tables['ed1page'] = document.createElement('table');
            Ed1.tables['ed1page'].setAttribute('id', 'ed1page');
            head = document.createElement('tr');
            head.insertAdjacentElement('beforeend', Ed1.render.th('Page', 'page_title'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('Issues on page', 'page_total', 'DESC'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('type', 'entity_type'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('Path', 'page_url'));
            Ed1.tables['ed1page'].insertAdjacentElement('beforeend', head);
            Ed1.tables['ed1page'].append(loadWrap.cloneNode('deep'));
            tableDetails = Ed1.render.details('Pages with issues', 'ed1page-title')
            Ed1.wrapPage.append(tableDetails);
            tableDetails.append(Ed1.tables['ed1page']);
            Ed1.tables['ed1page'].querySelectorAll('button').forEach((el) => {
                el.addEventListener('click', function () {
                    Ed1.reSort();
                    Ed1.get.ed1page(Ed1.buildRequest('ed1page'));
                });
            });

            Ed1.tables['ed1dismiss'] = document.createElement('table');
            Ed1.tables['ed1dismiss'].setAttribute('id', 'ed1dismiss');
            head = document.createElement('tr');
            head.insertAdjacentElement('beforeend', Ed1.render.th('Pages', 'page_title', 'DESC'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('Issue', 'result_key'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('Marked', 'dismissal_status'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('By', 'user'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('On', 'created'));
            head.insertAdjacentElement('beforeend', Ed1.render.th('Still on page', 'stale'));
            Ed1.tables['ed1dismiss'].insertAdjacentElement('beforeend', head);
            Ed1.tables['ed1dismiss'].append(loadWrap.cloneNode('deep'));
            tableDetails = Ed1.render.details('Dismissals', 'ed1dismiss-title')
            Ed1.wrapDismiss.append(tableDetails);
            tableDetails.append(Ed1.tables['ed1dismiss']);
            Ed1.tables['ed1dismiss'].querySelectorAll('th button').forEach((el) => {
                el.addEventListener('click', function () {
                    Ed1.reSort();
                    Ed1.get.ed1dismiss(Ed1.buildRequest('ed1dismiss'));
                });
            });

        }

        /**
         * Renderer for viewing results by test name.
         * @param {*} post 
         * @param {*} count 
         */
        Ed1.render.ed1result = function (post, count, announce) {

            Ed1.tables['ed1result'].querySelectorAll('tr + tr').forEach(el => {
                el.remove();
            })

            if (!!post) {
                if ( !Ed1.wrapResults.querySelector('nav') ) {
                    Ed1.render.pagination('ed1result', count, 50, 0, 'ed1result-title');
                }

                post.forEach((result) => {
                    let row = document.createElement('tr');

                    let keyName = ed11yLang.en[result['result_key']].title;
                    // URL sanitized on build...
                    let key = Ed1.render.td(keyName, false, Ed1.url + 'rkey=' + result['result_key'], false, 'rkey');
                    row.insertAdjacentElement('beforeend', key);

                    let pageCount = Ed1.render.td(result['count']);
                    row.insertAdjacentElement('beforeend', pageCount);

                    Ed1.tables['ed1result'].insertAdjacentElement('beforeend', row);
                })
            }

            if (announce) {
                Ed1.announce(post.length + " results");
            }

            Ed1.show();

        }

        /**
         * Renderer for viewing results by page.
         * @param {*} post 
         * @param {*} count 
         */
        Ed1.render.ed1page = function (post, count, announce) {

            Ed1.tables['ed1page'].querySelectorAll('tr + tr').forEach(el => {
                el.remove();
            })

            if (!!post) {
                if ( !Ed1.wrapPage.querySelector('nav') ) {
                    Ed1.render.pagination('ed1page', count, 50, 0, 'ed1page-title');
                }

                post.forEach((result) => {
                    let row = document.createElement('tr');

                    let pageLink = Ed1.render.td(result['page_title'], false, result['page_url'], result['pid']);
                    row.insertAdjacentElement('beforeend', pageLink);

                    let pageCount = Ed1.render.td(result['page_total']);
                    row.insertAdjacentElement('beforeend', pageCount);

                    let type = Ed1.render.td(result['entity_type'], false, `${Ed1.url}type=${result['entity_type']}`);
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

            Ed1.show();

        }

        /**
         * Renderer for viewing results by test name.
         * @param {*} post 
         * @param {*} count 
         */
        Ed1.render.ed1dismiss = function (post, count, announce) {

            Ed1.tables['ed1dismiss'].querySelectorAll('tr + tr').forEach(el => {
                el.remove();
            })

            if (!!post) {
                if ( !Ed1.wrapDismiss.querySelector('nav') ) {
                    Ed1.render.pagination('ed1dismiss', count, 50, 0, 'ed1dismiss-title');
                }

                if (post.length === 0) {
                    let notFound = Ed1.render.noResults('No alerts have been dismissed.', '6');
                    Ed1.tables['ed1dismiss'].insertAdjacentElement('beforeend', notFound);
                } else {
                    post.forEach((result) => {
                        /**
                         * created: "2022-12-09 15:27:45"
                            dismissal_status: "hide"
                            entity_type: "Post"
                            page_title: "Hello world!"
                            page_url: "https://editoria11y-wp.ddev.site/2022/10/03/hello-world/"
                            pid: "16"
                            result_key: "linkTextIsGeneric"
                            stale: "0"
                            updated: "2022-12-16 22:18:38"
                            user: "0"
                         */
                        let row = document.createElement('tr');
    
                        let pageLink = Ed1.render.td(result['page_title'], false, result['page_url'], result['pid']);
                        row.insertAdjacentElement('beforeend', pageLink);
                        // need to sanitize URL in response
                        let keyName = ed11yLang.en[result['result_key']].title;
                        let key = Ed1.render.td(keyName, false, Ed1.url + 'rkey=' + result['result_key'], false, 'rkey');
                        row.insertAdjacentElement('beforeend', key);
    
                        let marked = Ed1.render.td( result['dismissal_status'] );
                        row.insertAdjacentElement('beforeend', marked);
                        
                        let by = Ed1.render.td( result['user'] );
                        row.insertAdjacentElement('beforeend', by);
    
                        // todo: need to change this to created!!!
                        let on = Ed1.render.td( result['updated'] );
                        row.insertAdjacentElement('beforeend', on);
    
                        // old 
                        let stale = Ed1.render.td( !result['stale'] ? "No" : "Yes" );
                        row.insertAdjacentElement('beforeend', stale);
    
                        Ed1.tables['ed1dismiss'].insertAdjacentElement('beforeend', row);
                    })
                }

                
            }
                

            if (announce) {
                Ed1.announce(post.length + " results");
            }

            Ed1.show();

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
        Ed1.get.ed1dismiss = async function (action, announce = false) {
            fetch(wpApiSettings.root + 'ed11y/v1/' + action, Ed1.api,
            ).then(function (response) {
                return response.json();
            }).then(function (post) {
                if (post?.data?.status === 500) {
                    console.error(post.data.status + ': ' + post.message);
                } else {
                    Ed1.render.ed1dismiss(post[0], post[1], announce);
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


