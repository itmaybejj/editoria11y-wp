class Ed1 {
    constructor() {

        Ed1.wrap = document.getElementById('ed1');

        Ed1.dashboard = async function () {
            Ed1.getResults('dashboard');
        }

        Ed1.th = function (text, id) {
            let header = document.createElement('th');
            let headerLink = document.createElement('a');
            headerLink.setAttribute('href', encodeURI(id));
            headerLink.textContent = text;
            header.insertAdjacentElement('afterbegin', headerLink);
            return header;
        }

        Ed1.cell = function (content, url = false, cls = false) {
            let cell = document.createElement('td');
            if (!url) {
                cell.textContent = content;
            } else {
                cell.insertAdjacentElement('afterbegin', Ed1.link(url, content));
            }
            return cell;
        }

        Ed1.link = function (url, text) {
            let link = document.createElement('a');
            link.textContent = text;
            link.setAttribute('href', encodeURI(url));
            return link;
        }

        Ed1.renderResults = async function (post) {
            let table = document.createElement('table');
            let head = document.createElement('tr');
            head.insertAdjacentElement('beforeend', Ed1.th('Page', 'page_title'));
            head.insertAdjacentElement('beforeend', Ed1.th('Issues found', 'page_total'));
            head.insertAdjacentElement('beforeend', Ed1.th('type', 'entity_type'));
            head.insertAdjacentElement('beforeend', Ed1.th('Path', 'page_url'));
            table.insertAdjacentElement('beforeend', head);
            if (!!post) {
                post.forEach((result) => {
                    let row = document.createElement('tr');
                        
                        let pageLink = Ed1.cell( result['page_title'], result['page_url'] );
                        row.insertAdjacentElement('beforeend', pageLink);

                        let pageCount = Ed1.cell( result['page_total']);
                        row.insertAdjacentElement('beforeend', pageCount);

                        let type = Ed1.cell( result['entity_type'] );
                        row.insertAdjacentElement('beforeend', type);

                        let path = result['page_url'].replace(window.location.protocol + '//' + window.location.host, ''); 
                        path = Ed1.cell( path );
                        row.insertAdjacentElement('beforeend', path);

                    table.insertAdjacentElement('beforeend', row);
                })

            Ed1.wrap.insertAdjacentElement('beforeEnd', table);
        }
    }


        Ed1.getResults = function (action) {
            fetch(wpApiSettings.root  + 'ed11y/v1/' + action,{
                method: "GET",
                headers:{
                    'Content-Type': 'application/json',
                    'accept': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce,
                },
            }).then(function(response){
                return response.json();
            }).then(function(post){
                Ed1.renderResults(post);
                //console.log(post);
            });
        }
    }

    
}

new Ed1();
Ed1.dashboard();


