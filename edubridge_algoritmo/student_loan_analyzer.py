#!/usr/bin/env python3
"""
Analizador de préstamos para estudiantes de EduBridge

Este script permite analizar rangos de préstamos válidos para estudiantes
específicos basados en sus datos académicos almacenados en la base de datos.
"""

import pandas as pd
import argparse
from db_calculateLoan import student_loan_range_analysis, get_all_students



def main():
    parser = argparse.ArgumentParser(description='Analizador de rangos de préstamos para estudiantes')
    parser.add_argument('-i', '--id', type=int, help='ID del estudiante a analizar')
    parser.add_argument('-t', '--threshold', type=float, default=1.1,
                        help='Umbral de ganancia para considerar un préstamo rentable (default: 1.1)')
    parser.add_argument('-c', '--country', type=str, default='BR',
                        help='País para cálculos de riesgo (default: BR)')
    parser.add_argument('-l', '--list', action='store_true',
                        help='Listar todos los estudiantes disponibles')
    parser.add_argument('-o', '--output', type=str,
                        help='Guardar resultados en archivo CSV')
    parser.add_argument('-a', '--amount', type=float,
                        help='Monto específico de préstamo a analizar')
    parser.add_argument('-p', '--percentage', type=float, default=0.15,
                        help='Porcentaje del salario mensual usado para pagar el préstamo (default: 0.15)')
    parser.add_argument('-m', '--max_months', type=int, default=120,
                        help='Número máximo de meses para pagar el préstamo (default: 120)')
    parser.add_argument('-r', '--ratio', type=float, default=1.8,
                        help='Máxima proporción de pago total respecto al préstamo original (default: 1.8)')
    
    args = parser.parse_args()
    
    # Listar estudiantes si se solicita
    if args.list:
        students = get_all_students()
        if not students:
            print("No se encontraron estudiantes en la base de datos.")
            return
        
        print("\nEstudiantes disponibles:")
        print("--------------------------")
        for student in students:
            print(f"ID: {student['id']} - {student['nombre_completo']} - {student['universidad']} - {student['curso']}")
        return
    
    # Analizar estudiante específico o mostrar listado interactivo
    df = student_loan_range_analysis(
        student_id=args.id,
        profit_threshold=args.threshold,
        country=args.country,
        loan_amount=args.amount,
        salary_percentage=args.percentage, 
        max_months=args.max_months,
        max_payment_ratio=args.ratio
    )
    
    if df is not None:
        # Mostrar resultados
        with pd.option_context('display.max_colwidth', None):
            print("\nResultados del análisis de préstamos:")
            print("-------------------------------------")
            print(df.to_string(index=False))
        
        # Guardar resultados si se solicita
        if args.output:
            df.to_csv(args.output, index=False)
            print(f"\nResultados guardados en: {args.output}")

if __name__ == "__main__":
    main()