import numpy as np
import plotly.graph_objects as go

N=20

a = [0]*N

a[0]=0
a[1]=1
v=5

for n in range(2,N):
    a[n]=((n-2)*(n-1)-v*(v+1))/((n*(n-1)))*a[n-2]
for n in range(N):
    print(f"a_{n} = {a[n]}")

def series_func(x,coeffs):
    s=0
    for n, c in enumerate(coeffs):
        s+= c*(x**n)
    return s

# Generamos valores de x para evaluar la serie
x_vals = np.linspace(-2, 2, 400)
y_vals = [series_func(x, a) for x in x_vals]

# Para comparar, la función exacta: exp(x)
y_exact = np.exp(x_vals)

# Crear la figura con Plotly
fig = go.Figure()

fig.add_trace(go.Scatter(x=x_vals, y=y_vals,
                         mode='lines',
                         name='Aproximación de la serie'))
fig.add_trace(go.Scatter(x=x_vals, y=y_exact,
                         mode='lines',
                         name='exp(x)', line=dict(dash='dash')))

fig.update_layout(title="Aproximación de exp(x) con la serie de Taylor",
                  xaxis_title="x",
                  yaxis_title="y")

fig.show()