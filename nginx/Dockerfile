FROM nginx:1.27-alpine

COPY nginx/default.conf /etc/nginx/conf.d/default.conf
COPY app/public /var/www/html/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
