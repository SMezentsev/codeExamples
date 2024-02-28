(function () {
    'use strict';

    angular.module('app')
        .directive('treeView', treeViewDirective);

    function treeViewDirective($compile) {
        return {
            restrict: 'EA',
            replace: false,
            scope: {
                data: '=',
                selectedItem: '=?',
                onItemSelected: '&?',
                nodeTemplateId: '@'
            },
            template: "<ol class=\"tree-view\">" +
                "<li ng-repeat=\"node in nodes\" tabindex=\"{{$index}}\" ng-keydown=\"onKeyDown($event)\" ng-include=\"'' + nodeTemplateId + ''\"></li>" +
                "</ol>",
            link: function ($scope,element) {
                var keyCodes = {
                    leftArrow: 37,
                    upArrow: 38,
                    rightArrow: 39,
                    downArrow: 40
                };

                $scope.$watch('data', function(data) {
                    $scope.nodes = initializeNodes(data);
                });

                $scope.hasChildren = hasChildren;
                $scope.isBranch = isBranch;
                $compile($(element).contents())($scope);

                $scope.selectNode = function (node) {

                    if ($scope.isBranch(node)) {
                        node.expanded = !node.expanded;
                    }
                    else {
                        $scope.selectedItem = node.value;
                        $scope.onItemSelected({node: node.value});
                    }

                    $scope.selectedNode = node;
                };

                $scope.countChildrenRecursive = function (node) {
                    var count = 0;

                    node.children.forEach(function (n) {
                        if ($scope.hasChildren(n)) {
                            count += $scope.countChildrenRecursive(n);
                            return;
                        }

                        count++;
                    });

                    return count;
                };

                $scope.onKeyDown = function ($event) {
                    var selectedNode = $scope.selectedNode;

                    if ($event.which === keyCodes.upArrow && selectedNode.previous) {
                        if (isBranch(selectedNode.previous)) { $scope.selectedNode = selectedNode.previous; }
                        else { $scope.selectNode(selectedNode.previous); }
                    }
                    if ($event.which === keyCodes.downArrow && selectedNode.next) {
                        if (isBranch(selectedNode.next)) { $scope.selectedNode = selectedNode.next; }
                        else { $scope.selectNode(selectedNode.next); }
                    }
                    if ($event.which === keyCodes.leftArrow && selectedNode.parent) {
                        selectedNode.parent.expanded = false;
                        $scope.selectedNode = selectedNode.parent;
                    }
                    if ($event.which === keyCodes.rightArrow && hasChildren(selectedNode)) {
                        selectedNode.expanded = true;
                        $scope.selectNode(selectedNode.children[0]);
                    }

                    $event.stopPropagation();
                };

                function hasChildren(node) {
                    return Array.isArray(node.children) && node.children.length > 0;
                }

                function isBranch(node) {
                    return Array.isArray(node.children);
                }

                function initializeNodes(data, parent) {
                    var nodes = [];

                    for (var i = 0; i < data.length; i++) {
                        var node = { value: data[i], expanded: false };

                        if (parent) { node.parent = parent; }
                        if (i > 0) {
                            node.previous = nodes[i - 1];
                            nodes[i - 1].next = node;
                        }

                        if (isBranch(data[i])) {
                            node.children = initializeNodes(data[i].children, node);
                        }

                        if ($scope.selectedItem === node.value) {
                            $scope.selectedNode = node;
                            var nodeToExpand = node.parent;
                            while (nodeToExpand != null) {
                                nodeToExpand.expanded = true;
                                nodeToExpand = nodeToExpand.parent;
                            }
                        }

                        nodes.push(node);

                    }



                    return nodes;
                }
            }
        };
    }


}());



