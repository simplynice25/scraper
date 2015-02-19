// JavaScript Document

(function(){

	$('#menuModal').on('hide.bs.modal', function(){
		$('.btn-dynamic-menu').removeClass('btn-sell btn-neutral btn-buy');
		$('.title-dynamic-menu').removeClass('title-sell title-neutral title-buy');
	})

	var funcConf = {
		actionsBtn: ".actions-btn .btn",
		Loader: "fa fa-spin fa-spinner",
		actions: {
			1: {
				0: config._actions,
				1: "btn-danger",
				2: "Remove from watchlist"
			},
			2: {
				0: config._actions,
				1: "btn-danger",
				2: "Remove from portfolio"
			},
			3: {
				0: config._actions, // config._delete
				1: "btn-danger",
				2: "Unhide"
			},
			4: {
				0: config._delete,
			},
			_default: 0
		},
		editLink: ".edit-link",
		linkModal: "#newLink",
		pastData: "#pastData",
		dynamicMenu: ".show-btn .btn",
	}
	
	var funcInit = {
		dynamicMenu: function(){
			return this.delegate(funcConf.dynamicMenu, "click", function(){
				var self = $(this),
					menuData = self.data('menu');

					$('.title-dynamic-menu').text(menuData.txt).addClass(menuData.color);
					var txt = (menuData.txt == 'VIEW ALL') ? '' : ' ' + menuData.txt;
					$('.btn-dynamic-menu').text('VIEW ALL' +txt).addClass(menuData.btn).attr('href', menuData.url);

					$('.subsector-anchor').each(function(){
						$(this).attr('href', menuData.url + '&subsector=' + $(this).data('subsector'));
					})
			})
		},
		actionsInit: function(){
			return this.delegate(funcConf.actionsBtn, "click", function(){
				
				if ($(this).hasClass('edit-link')) return false;
				
				if ( ! confirm("Are you sure you want to do this action?"))
				{
					return false;
				}
				
				$(funcConf.actionsBtn).attr("disabled", true);
				var _self = $(this), _class, _action = _self.data('action');

				_class = _self.children("i.fa").attr("class");
				_self.children("i.fa").removeClass(_class).addClass(funcConf.Loader);
/*
				setTimeout(function(){
					
					var _objData = {"id": _action['id'], "method": _action['method'], "type": 0};

					_self
						.attr('title', funcConf.actions[_action['method']][2])
						.tooltip('fixTitle')
						.removeClass("btn-default")
						.addClass(funcConf.actions[_action['method']][1])
						.data("action", _objData);
					
					
					$(".actions-btn .btn").tooltip();

					_self.children("i.fa").removeClass(funcConf.Loader).addClass(_class);
					$(funcConf.actionsBtn).attr("disabled", false);
				}, 2000);
				
				return false;
*/

				$.get(funcConf.actions[_action['method']][0], { id: _action['id'], method: _action['method'], type: _action['type'] })
				.done(function( data )
				{
					if (_action['method'] == 4)
					{
						_self.parent().parent().parent().remove();
						return false;
					}

					location.href = config._currUrl;
				})
				.fail(function()
				{
					console.log('Failed to process ...');
				})
				.always(function()
				{
					if (_action['method'] == 4)
					{
						_self.children("i.fa").removeClass(funcConf.Loader).addClass(_class);
						$(funcConf.actionsBtn).attr("disabled", false);
					}
				})
			});
		},
		pastDataShow: function(){
			return this.delegate(funcConf.pastData, "shown.bs.modal", function(e){
				var _self = $(this), _triggerTxt = $(e.relatedTarget).text();
				
				var _link = _self.find("a.show-all").attr("href");
				
				_self.find(".modal-body").html("Loading ...");
				_self.find("a.show-all").attr("href", _link + "?abbr=" + _triggerTxt)

				$.get(config._pastData, { abbr: _triggerTxt })
				.done(function( data ) {
					_self.find(".modal-body").html( data );
                    
                    var _title = $('.past-data-title').data('title');
                    _self.find("#myModalLabel").html(_title);
					$('#scrapedTable2').tablesorter({
			            textExtraction: function(node){ 
			                var cell_value = $(node).text();
			                var sort_value = $(node).data('traded');
			                return (sort_value != undefined) ? sort_value : cell_value;
			            },
			            //sortList: [[1,1]]
			        });
				})
				.fail(function(){
					console.log("Something went wrong!");
				});
			})
		},
		pastDataHide: function(){
			return this.delegate(funcConf.pastData, "hide.bs.modal", function(e){
				var _link = $(funcConf.pastData + ' .show-all').data('href');
				$(funcConf.pastData + ' .show-all').attr('href', _link);
			})
		},
		loadModal: function() {
			return this.delegate(funcConf.linkModal, "hide.bs.modal", function(){
				var _self = $(this),
					_link = $('form[name=link-action]').data('href');

				$('input[name=id]').val('');
				$('input[name=code]').val('');
				$('input[name=name]').val('');
				$('input[name=link]').val('');
				//$('select[name=category] option[value=0]').attr('selected', true);
				$('select[name=category]').val('');
				$('input[name=label]').tagsinput('removeAll');
				
				$('form[name=link-action]').attr('action', _link)
			})
		},
		editLink: function() {
			return this.delegate(funcConf.editLink, "click", function(){
				var _self = $(this),
					_parentRow = _self.parent('div').parent('td').parent('tr').attr('id'),
					_category = _self.parent('div').parent('td').parent('tr').data('category'),
					_data = $('#' + _parentRow + ' td').map(function(){ return $(this).text() }),
					_link = $('form[name=link-action]').data('href')
					_id = $('#' + _parentRow).data('id'),
                    _labels = _self.parent('div').parent('td').parent('tr').find('td.td-labels').data('labels')
                    _type = _self.data('type');
					
					$('input[name=id]').val(_id);
					_category = (_category > 0) ? _category : 0;
                    if (_type == 1)
                    {
                        $('input[name=code]').val(_data[0]);
                        $('input[name=name]').val(_data[1]);
                        $('input[name=link]').val(_data[2]);
                        $('select[name=category] option[value='+_category+']').prop('selected', true);
                    } else {
                        $('input[name=code]').removeAttr('required').parent().hide();
                        $('input[name=name]').removeAttr('required').parent().hide();
                        $('input[name=link]').removeAttr('required').parent().hide();
                        $('select[name=category]').removeAttr('required').parent().hide();
                    }
					$('input[name=label]').tagsinput('removeAll');
					$('input[name=label]').tagsinput('add', _labels);
					
					$('form[name=link-action]').attr('action', _link + '?edit=true')
			})
		}
	}
	
	$.extend(config.doc, funcInit);
	config.doc.actionsInit();
	config.doc.loadModal();
	config.doc.editLink();
	config.doc.pastDataShow();
	config.doc.pastDataHide();
	config.doc.dynamicMenu();

})(jQuery,window,document)