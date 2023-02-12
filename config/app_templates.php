<?php

return [
	// forms
	'input' => '<input type="{{type}}" name="{{name}}" class="form-control"{{attrs}}/>{{after}}',
	'inputContainer' => '<div class="form-group {{type}}{{divClass}}{{required}}"{{divId}}>{{content}}</div>',
	'inputContainerError' => '<div class="form-group {{type}}{{divClass}}{{required}} error">{{content}}{{error}}</div>',
	'checkboxContainer' => '<div class="{{type}}{{divClass}}{{required}}"{{divId}}>{{content}}</div>',
	'radioContainer' => '<div class="{{divClass}}{{required}}"{{divId}}>{{content}}</div>',
	'nestingLabel' => '{{hidden}}{{input}}<label{{attrs}}>{{text}}</label>',
	'radioWrapper' => '<div class="radio">{{label}}</div>',
	'error' => '<p class="text-danger" id="{{id}}">{{content}}</p>',


	// paginator
	'prevActive' => '<li><a class="prev" rel="prev" href="{{url}}">{{text}}</a></li>',
	'prevDisabled' => '<li class="disabled"><span class="prev">{{text}}</span></li>',
	'nextActive' => '<li><a class="next" rel="next" href="{{url}}">{{text}}</a></li>',
	'nextDisabled' => '<li class="disabled"><span class="next">{{text}}</span></li>',
];
