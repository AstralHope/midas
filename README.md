# MIDAS Middleware Integrated Diagnostic Analysis System
## Midas touch 为中间件运维点石成金
一个php的文本展示页

还在不断完善中，完成情况如下：
- [x] 展示各ECS中运行的容器信息
- [x] 展示每朵云下的集群信息
- [x] 小工具：键入IP后自动提示所属云，如果为pod IP或ACK的宿主机IP，同时提示所属集群与该集群master
- [x] sofamq信息展示
- [ ] 同步cmdb应用维护人员信息便于快速寻找A角
- [ ] 常见现象处理知识库


## 容器化部署

在项目目录下新增deskecc目录放入数据文件

```shell
cd docker/nginx
docker build -t midas-nginx:1.30.0 .
cd ../php
docker build -t  midas-php:8.2-fpm .
cd ..
docker compose up -d
```
